<?php

namespace App\Scraping\Fetchers;

use App\Models\ScanSource;
use App\Scraping\FetchResult;

interface Fetcher
{
    public function fetch(ScanSource $source): FetchResult;
}
