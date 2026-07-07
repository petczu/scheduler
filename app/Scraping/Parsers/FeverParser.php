<?php

namespace App\Scraping\Parsers;

use App\Models\ScanSource;
use App\Models\SlotSnapshot;
use App\Scraping\ParsedSlot;
use Carbon\CarbonImmutable;

/**
 * Parser for Fever (feverup.com) event pages, e.g. https://feverup.com/m/522768.
 *
 * The server-rendered page embeds every session as JSON fragments:
 *
 *   "default_label":"SEVEN"  ... {"starts_at_iso":"2026-07-07T12:00:00+04:00",
 *   ..."available_tickets":0,"has_available_tickets":false,...}
 *
 * Sessions are grouped under room labels (default_label). A session with
 * zero available tickets is sold out. Duplicated fragments are collapsed
 * by the standard room+datetime dedupe in ScanService/parsers.
 */
class FeverParser implements SlotParser
{
    public function parse(string $html, ScanSource $source): array
    {
        $timezone = $source->venue->timezone;

        // Room label anchors, in document order.
        preg_match_all('/"default_label"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/', $html, $labelMatches, PREG_OFFSET_CAPTURE);

        // Session fragments: starts_at_iso followed (closely) by available_tickets.
        preg_match_all(
            '/"starts_at_iso"\s*:\s*"([^"]+)".{0,600}?"available_tickets"\s*:\s*(\d+)/s',
            $html,
            $sessionMatches,
            PREG_OFFSET_CAPTURE,
        );

        $labels = $labelMatches[1] ?? [];
        $slots = [];
        $seen = [];

        foreach ($sessionMatches[1] as $i => [$iso, $offset]) {
            try {
                $slotAt = CarbonImmutable::parse($iso)->setTimezone($timezone);
            } catch (\Throwable) {
                continue;
            }

            $label = $this->nearestLabelBefore($labels, $offset);
            $tickets = (int) $sessionMatches[2][$i][0];
            $status = $tickets > 0 ? SlotSnapshot::STATUS_AVAILABLE : SlotSnapshot::STATUS_SOLD_OUT;

            $key = $label.'|'.$slotAt->format('Y-m-d H:i');
            if (isset($seen[$key]) && $status !== SlotSnapshot::STATUS_SOLD_OUT) {
                continue;
            }

            $seen[$key] = true;
            $slots[$key] = new ParsedSlot($slotAt, $status, "tickets: {$tickets}", $label);
        }

        return array_values($slots);
    }

    /**
     * @param  array<int, array{0: string, 1: int}>  $labels
     */
    protected function nearestLabelBefore(array $labels, int $offset): ?string
    {
        $best = null;

        foreach ($labels as [$label, $labelOffset]) {
            if ($labelOffset > $offset) {
                break;
            }
            $best = $label;
        }

        return $best !== null ? stripcslashes($best) : null;
    }
}
