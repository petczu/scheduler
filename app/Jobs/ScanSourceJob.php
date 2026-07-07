<?php

namespace App\Jobs;

use App\Models\ScanSource;
use App\Scraping\ScanService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ScanSourceJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 180;

    public function __construct(
        public ScanSource $source,
    ) {}

    public function handle(ScanService $scanner): void
    {
        $scanner->scan($this->source);
    }
}
