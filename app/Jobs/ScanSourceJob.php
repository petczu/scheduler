<?php

namespace App\Jobs;

use App\Models\ScanSource;
use App\Scraping\ScanService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class ScanSourceJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 180;
    public int $backoff = 30;

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

    /**
     * Scrapfly enforces a low concurrency limit (Discovery plan = 5). Now that
     * scans run on the queue in parallel, serialize Scrapfly requests to one at
     * a time to avoid HTTP 429s. Free HTTP sources are unthrottled.
     */
    public function middleware(): array
    {
        if ($this->source->fetcher === 'scrapfly') {
            return [
                (new WithoutOverlapping('scrapfly'))
                    ->releaseAfter(15)
                    ->expireAfter(180),
            ];
        }

        return [];
    }

    public function handle(ScanService $scanner): void
    {
        $scanner->scan($this->source);
    }
}
