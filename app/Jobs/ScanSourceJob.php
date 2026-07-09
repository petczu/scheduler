<?php

namespace App\Jobs;

use App\Models\ScanSource;
use App\Scraping\ScanService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ScanSourceJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 180;

    public function __construct(
        public ScanSource $source,
    ) {}

    /**
     * One queued/running job per source at a time, so a queue backlog never
     * double-scans a source (which would also double-spend Scrapfly credits).
     */
    public function uniqueId(): string
    {
        return 'scan-source-'.$this->source->id;
    }

    // Safety cap on the uniqueness lock (a little over the job timeout).
    public int $uniqueFor = 300;

    public function handle(ScanService $scanner): void
    {
        $scanner->scan($this->source);
    }
}
