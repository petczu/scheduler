<?php

namespace App\Scraping\Parsers;

use Carbon\CarbonImmutable;

trait ExtractsSlotTimes
{
    /**
     * Extract a time like "19:30", "7.30 pm", "7:30PM" from arbitrary text.
     * Returns [hour, minute] in 24h format, or null.
     */
    protected function extractTime(string $text): ?array
    {
        if (! preg_match('/(\d{1,2})[:.](\d{2})\s*(am|pm)?/i', $text, $m)) {
            return null;
        }

        $hour = (int) $m[1];
        $minute = (int) $m[2];
        $meridiem = strtolower($m[3] ?? '');

        if ($meridiem === 'pm' && $hour < 12) {
            $hour += 12;
        } elseif ($meridiem === 'am' && $hour === 12) {
            $hour = 0;
        }

        if ($hour > 23 || $minute > 59) {
            return null;
        }

        return [$hour, $minute];
    }

    protected function combineDateAndTime(CarbonImmutable $date, array $time): CarbonImmutable
    {
        return $date->setTime($time[0], $time[1]);
    }
}
