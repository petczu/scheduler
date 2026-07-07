<?php

namespace App\Scraping\Parsers;

use App\Models\ScanSource;
use App\Models\SlotSnapshot;
use App\Scraping\ParsedSlot;
use Carbon\CarbonImmutable;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Parser for Bookeo booking pages (b_*_start.html), based on the real
 * rendered markup (see storage/app/scans/ for captured samples).
 *
 * A location page lists one card per game:
 *
 *   <div class="cbtce_box">
 *       ...<div class="cbtce_boxTitle">Orient Express</div>...
 *       <div class="cbtce_boxButtons singledate">
 *           <button class="cbtce_boxSlot cbtce_boxSlot_actionable"
 *                   onclick="cbtce_submit('TYPE-SLOT-2026-07-07');...">
 *               <div class="cbtce_boxSlotTime">10:45 AM</div>
 *           </button>
 *           <button class="cbtce_boxSlot cbtce_boxSlot_eventLabel_full" disabled="disabled">
 *               <div class="cbtce_boxSlotTime">4:45 PM</div>
 *               <div class="cbtce_actionInfo">SOLD OUT</div>
 *           </button>
 *       </div>
 *   </div>
 *
 * Each slot is tagged with its card title (roomLabel) so one location scan
 * feeds every room. The slot's date is embedded in the onclick payload of
 * actionable slots; sold-out slots fall back to the page-level date.
 * Overrides from parser_config (slot_selector, busy_matchers) are respected.
 */
class BookeoParser extends GenericParser
{
    protected const DEFAULT_CARD_SELECTOR = '[class="cbtce_box"], [class*="cbtce_box "]';
    protected const DEFAULT_CARD_TITLE_SELECTOR = '[class*="cbtce_boxTitle"]';

    // Bookeo ships two slot widgets:
    //  - cbtce_boxSlot   (Game Over: date list, busy = button class eventLabel_full)
    //  - fixedEventSlot  (Escape The Room: single-day list, busy = nested .eventLabel_full)
    protected const DEFAULT_SLOT_SELECTOR = 'button[class*="cbtce_boxSlot"], button[class*="fixedEventSlot"]';

    protected const DEFAULT_BUSY_MATCHERS = [
        ['type' => 'class', 'value' => 'eventLabel_full'],       // cbtce: on the button
        ['type' => 'css', 'value' => '.eventLabel_full'],        // fixedEventSlot: nested div
        ['type' => 'css', 'value' => '.eventLabel_soldout'],
        ['type' => 'attr', 'name' => 'disabled', 'value' => 'disabled'],
        ['type' => 'text', 'value' => 'sold out'],
        ['type' => 'text', 'value' => 'not available'],
        ['type' => 'text', 'value' => 'fully booked'],
    ];

    public function parse(string $html, ScanSource $source): array
    {
        $config = $source->parser_config ?? [];
        $config['slot_selector'] ??= self::DEFAULT_SLOT_SELECTOR;
        $config['busy_matchers'] ??= self::DEFAULT_BUSY_MATCHERS;
        $cardSelector = $config['card_selector'] ?? self::DEFAULT_CARD_SELECTOR;
        $titleSelector = $config['card_title_selector'] ?? self::DEFAULT_CARD_TITLE_SELECTOR;

        $today = CarbonImmutable::now($source->venue->timezone)->startOfDay();
        $pageDate = $this->extractPageDate($html, $today) ?? $today;

        $crawler = new Crawler($html);
        $slots = [];

        $cards = $crawler->filter($cardSelector);

        if ($cards->count() > 0) {
            $cards->each(function (Crawler $card) use ($config, $titleSelector, $pageDate, $today, &$slots) {
                $titleNode = $card->filter($titleSelector);
                $title = $titleNode->count() > 0 ? trim($titleNode->first()->text('')) : null;
                $slots = array_merge(
                    $slots,
                    $this->parseBookeoSlots($card, $config, $pageDate, $today, $title ?: null),
                );
            });
        } else {
            // Single-game page (?type=...) without a card wrapper.
            $slots = $this->parseBookeoSlots($crawler, $config, $pageDate, $today, null);
        }

        return $this->dedupe($slots);
    }

    /**
     * @return ParsedSlot[]
     */
    protected function parseBookeoSlots(Crawler $scope, array $config, CarbonImmutable $pageDate, CarbonImmutable $today, ?string $roomLabel): array
    {
        $slots = [];

        $scope->filter($config['slot_selector'])->each(function (Crawler $node) use ($config, $pageDate, $today, $roomLabel, &$slots) {
            $label = trim(preg_replace('/\s+/', ' ', $node->text('')));
            $time = $this->extractTime($label);

            if ($time === null) {
                return;
            }

            $date = $this->extractSlotDate($node, $today->timezone->getName()) ?? $pageDate;
            $matched = $this->matchesAny($node, $label, $config['busy_matchers']);

            $slots[] = new ParsedSlot(
                $this->combineDateAndTime($date, $time),
                $matched ? SlotSnapshot::STATUS_SOLD_OUT : SlotSnapshot::STATUS_AVAILABLE,
                mb_substr($label, 0, 255),
                $roomLabel,
            );
        });

        return $slots;
    }

    /**
     * Actionable slots carry their date in the onclick payload:
     * cbtce_submit('3254JML...-2026-07-07').
     */
    protected function extractSlotDate(Crawler $node, string $timezone): ?CarbonImmutable
    {
        // cbtce_submit('TYPE-SLOT-2026-07-07') and
        // cbTimeFixed_onSlotClick('2026-07-07','14:45',...) both carry the date.
        $onclick = $node->attr('onclick') ?? '';

        if (preg_match('/(\d{4}-\d{2}-\d{2})/', $onclick, $m)) {
            return CarbonImmutable::parse($m[1], $timezone)->startOfDay();
        }

        return null;
    }

    /**
     * Page-level date, used for sold-out slots that have no onclick payload.
     * Prefer a date from any cbtce_submit call (all slots on a "singledate"
     * page share the day), then fall back to a human-readable date heading.
     */
    protected function extractPageDate(string $html, CarbonImmutable $today): ?CarbonImmutable
    {
        if (preg_match("/cbtce_submit\('[^']*?(\d{4}-\d{2}-\d{2})'/", $html, $m)) {
            return CarbonImmutable::parse($m[1], $today->timezone)->startOfDay();
        }

        $patterns = [
            '/([A-Z][a-z]+day),?\s+(\d{1,2})\s+([A-Z][a-z]+)\s+(\d{4})/', // Tue, 7 July 2026
            '/([A-Z][a-z]+day),?\s+([A-Z][a-z]+)\s+(\d{1,2}),?\s+(\d{4})/', // Tuesday, July 7, 2026
            '/(\d{1,2})\s+([A-Z][a-z]+)\s+(\d{4})/',                        // 7 July 2026
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $m)) {
                try {
                    return CarbonImmutable::parse($m[0], $today->timezone)->startOfDay();
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        return null;
    }
}
