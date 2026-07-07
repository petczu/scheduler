<?php

namespace App\Scraping\Parsers;

use App\Models\ScanSource;
use App\Models\SlotSnapshot;
use App\Scraping\ParsedSlot;
use Carbon\CarbonImmutable;

/**
 * Parser for the WordPress "Quest Game Booking" (QGB) plugin used by
 * questa.ae. Everything lives in the product page:
 *
 *  - weekly slot grid:  <div class="qgb-wrap" ... data-open='{"mon":["10:15",...],...}'>
 *  - booked slots map:  window.QGBBookedSlots = {"2026-07-07":["10:15"],...} || {};
 *
 * One free HTTP request yields the schedule for the days ahead. Slots are
 * generated from the weekly grid for `days_ahead` days (default 7); a slot
 * is sold out when it appears in the booked map.
 */
class QgbParser implements SlotParser
{
    public function parse(string $html, ScanSource $source): array
    {
        $grid = $this->extractGrid($html);

        if ($grid === []) {
            return [];
        }

        $booked = $this->extractBookedMap($html);
        $daysAhead = (int) ($source->parser_config['days_ahead'] ?? 7);
        $today = CarbonImmutable::now($source->venue->timezone)->startOfDay();
        $slots = [];

        for ($i = 0; $i < $daysAhead; $i++) {
            $date = $today->addDays($i);
            $dow = strtolower($date->format('D')); // mon, tue, ...
            $dateKey = $date->format('Y-m-d');

            foreach ($grid[$dow] ?? [] as $time) {
                if (! preg_match('/^\d{1,2}:\d{2}$/', (string) $time)) {
                    continue;
                }

                [$hour, $minute] = explode(':', $time);
                $busy = in_array($time, $booked[$dateKey] ?? [], true);

                $slots[] = new ParsedSlot(
                    $date->setTime((int) $hour, (int) $minute),
                    $busy ? SlotSnapshot::STATUS_SOLD_OUT : SlotSnapshot::STATUS_AVAILABLE,
                    $time,
                );
            }
        }

        return $slots;
    }

    /**
     * @return array<string, string[]> weekday slug => times
     */
    protected function extractGrid(string $html): array
    {
        if (! preg_match("/data-open='([^']+)'/", $html, $m)
            && ! preg_match('/data-open="([^"]+)"/', $html, $m)) {
            return [];
        }

        $decoded = json_decode(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, string[]> Y-m-d => booked times
     */
    protected function extractBookedMap(string $html): array
    {
        if (! preg_match('/QGBBookedSlots\s*=\s*(\{.*?\}|\[.*?\])\s*(?:\|\||;)/s', $html, $m)) {
            return [];
        }

        $decoded = json_decode($m[1], true);

        return is_array($decoded) ? $decoded : [];
    }
}
