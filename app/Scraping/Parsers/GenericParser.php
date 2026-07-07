<?php

namespace App\Scraping\Parsers;

use App\Models\ScanSource;
use App\Models\SlotSnapshot;
use App\Scraping\ParsedSlot;
use Carbon\CarbonImmutable;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Configurable parser driven by ScanSource::parser_config:
 *
 * {
 *   "slot_selector": ".time-slot",          // required: one element per slot
 *   "card_selector": ".room-card",          // optional: multi-room pages
 *   "card_title_selector": ".room-title",   // required with card_selector
 *   "json_html_path": "data.html",          // optional: response is JSON, HTML lives at this path
 *   "http_headers": {"X-Requested-With": "XMLHttpRequest"}, // optional: extra fetch headers
 *   "datetime_attr": "data-datetime",       // optional: attr with full date+time
 *   "date_attr": "data-date",               // optional: attr with the slot's date
 *   "time_attr": "data-time",               // optional: attr with the slot's time
 *   "busy_matchers": [                      // used when parse_mode = detect_busy
 *     {"type": "text",  "value": "SOLD OUT"},
 *     {"type": "class", "value": "disabled"},
 *     {"type": "css",   "value": ".badge-sold"},
 *     {"type": "attr",  "name": "aria-disabled", "value": "true"},
 *     {"type": "style", "value": "line-through"}
 *   ],
 *   "free_matchers": [ ... ]                // used when parse_mode = detect_free
 * }
 *
 * parse_mode on the ScanSource decides the default:
 *  - detect_busy: slot is available unless a busy matcher hits
 *  - detect_free: slot is sold_out unless a free matcher hits
 *
 * With card_selector set, slots are grouped per card and tagged with the
 * card's title (roomLabel), so one page can feed many rooms. Without it,
 * the whole page is treated as a single room (roomLabel = null).
 *
 * When no date can be extracted, the slot is assumed to belong to the
 * current day in the venue's timezone (typical "today" booking widgets).
 */
class GenericParser implements SlotParser
{
    use ExtractsSlotTimes;

    public function parse(string $html, ScanSource $source): array
    {
        $config = $source->parser_config ?? [];

        if (blank($config['slot_selector'] ?? null)) {
            return [];
        }

        $html = $this->unwrapJson($html, $config);

        $today = CarbonImmutable::now($source->venue->timezone)->startOfDay();
        $crawler = new Crawler($html);
        $slots = [];

        if (! blank($config['card_selector'] ?? null)) {
            $crawler->filter($config['card_selector'])->each(function (Crawler $card) use ($config, $source, $today, &$slots) {
                $title = trim($card->filter($config['card_title_selector'] ?? 'h1, h2, h3')->first()->text(''));
                $slots = array_merge($slots, $this->parseSlots($card, $config, $source, $today, $title ?: null));
            });
        } else {
            $slots = $this->parseSlots($crawler, $config, $source, $today, null);
        }

        return $this->dedupe($slots);
    }

    /**
     * @return ParsedSlot[]
     */
    protected function parseSlots(Crawler $scope, array $config, ScanSource $source, CarbonImmutable $today, ?string $roomLabel): array
    {
        $slots = [];

        $scope->filter($config['slot_selector'])->each(function (Crawler $node) use ($config, $source, $today, $roomLabel, &$slots) {
            $label = trim(preg_replace('/\s+/', ' ', $node->text('')));

            $slotAt = $this->resolveDateTime($node, $config, $today);
            if ($slotAt === null) {
                return;
            }

            $matchers = $source->parse_mode === 'detect_free'
                ? ($config['free_matchers'] ?? [])
                : ($config['busy_matchers'] ?? []);

            $matched = $this->matchesAny($node, $label, $matchers);

            $status = $source->parse_mode === 'detect_free'
                ? ($matched ? SlotSnapshot::STATUS_AVAILABLE : SlotSnapshot::STATUS_SOLD_OUT)
                : ($matched ? SlotSnapshot::STATUS_SOLD_OUT : SlotSnapshot::STATUS_AVAILABLE);

            $slots[] = new ParsedSlot($slotAt, $status, mb_substr($label, 0, 255), $roomLabel);
        });

        return $slots;
    }

    /**
     * Some endpoints return JSON with the schedule HTML inside
     * (e.g. {"data": {"schedule_partial": "<section>..."}}).
     */
    protected function unwrapJson(string $html, array $config): string
    {
        if (blank($config['json_html_path'] ?? null)) {
            return $html;
        }

        $decoded = json_decode($html, true);

        return is_array($decoded)
            ? (string) data_get($decoded, $config['json_html_path'], '')
            : $html;
    }

    protected function resolveDateTime(Crawler $node, array $config, CarbonImmutable $today): ?CarbonImmutable
    {
        if (! blank($config['datetime_attr'] ?? null)) {
            $raw = $node->attr($config['datetime_attr']);
            if (! blank($raw)) {
                try {
                    return CarbonImmutable::parse($raw, $today->timezone)->shiftTimezone($today->timezone);
                } catch (\Throwable) {
                    // fall through to text extraction
                }
            }
        }

        $date = $today;
        if (! blank($config['date_attr'] ?? null)) {
            $raw = $node->attr($config['date_attr']);
            if (! blank($raw)) {
                try {
                    $date = CarbonImmutable::parse($raw, $today->timezone)->startOfDay();
                } catch (\Throwable) {
                    // keep today
                }
            }
        }

        $time = null;
        if (! blank($config['time_attr'] ?? null)) {
            $time = $this->extractTime((string) $node->attr($config['time_attr']));
        }
        $time ??= $this->extractTime($node->text(''));

        return $time === null ? null : $this->combineDateAndTime($date, $time);
    }

    protected function matchesAny(Crawler $node, string $label, array $matchers): bool
    {
        foreach ($matchers as $matcher) {
            $type = $matcher['type'] ?? 'text';
            $value = $matcher['value'] ?? '';

            $hit = match ($type) {
                'text' => $value !== '' && mb_stripos($label, $value) !== false,
                'class' => str_contains(' '.($node->attr('class') ?? '').' ', $value),
                'css' => $node->filter($value)->count() > 0,
                'attr' => ($node->attr($matcher['name'] ?? '') ?? null) === $value,
                'style' => mb_stripos($node->attr('style') ?? '', $value) !== false,
                default => false,
            };

            if ($hit) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  ParsedSlot[]  $slots
     * @return ParsedSlot[]
     */
    protected function dedupe(array $slots): array
    {
        $result = [];

        foreach ($slots as $slot) {
            $key = ($slot->roomLabel ?? '').'|'.$slot->slotAt->format('Y-m-d H:i');
            // A sold_out reading wins over available for the same time:
            // duplicated markup often nests a "SOLD OUT" badge inside the slot.
            if (! isset($result[$key]) || $slot->status === SlotSnapshot::STATUS_SOLD_OUT) {
                $result[$key] = $slot;
            }
        }

        return array_values($result);
    }
}
