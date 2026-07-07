<?php

namespace App\Scraping;

use Carbon\CarbonImmutable;

class ParsedSlot
{
    public function __construct(
        // Slot start in the venue's local timezone
        public readonly CarbonImmutable $slotAt,
        // SlotSnapshot::STATUS_*
        public readonly string $status,
        public readonly ?string $rawLabel = null,
        // Card title on multi-room pages (null on single-room pages)
        public readonly ?string $roomLabel = null,
    ) {}
}
