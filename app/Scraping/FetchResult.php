<?php

namespace App\Scraping;

class FetchResult
{
    public function __construct(
        public readonly string $html,
        public readonly string $finalUrl,
        public readonly string $fetcher,
        public readonly ?int $creditsCost = null,
    ) {}
}
