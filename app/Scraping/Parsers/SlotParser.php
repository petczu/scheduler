<?php

namespace App\Scraping\Parsers;

use App\Models\ScanSource;

interface SlotParser
{
    /**
     * @return \App\Scraping\ParsedSlot[]
     */
    public function parse(string $html, ScanSource $source): array;
}
