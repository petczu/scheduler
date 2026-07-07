<?php

namespace App\Scraping\Fetchers;

use App\Models\ScanSource;
use App\Scraping\FetchResult;
use Illuminate\Support\Facades\Http;

/**
 * Plain HTTP fetch for unprotected sites. Free, but cannot run JavaScript.
 */
class HttpFetcher implements Fetcher
{
    public function fetch(ScanSource $source): FetchResult
    {
        // Extra headers per source (e.g. X-Requested-With for AJAX endpoints).
        $extraHeaders = $source->parser_config['http_headers'] ?? [];

        $response = Http::withHeaders(array_merge([
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9',
        ], $extraHeaders))
            ->timeout(30)
            ->get($source->resolvedUrl())
            ->throw();

        return new FetchResult(
            html: $response->body(),
            finalUrl: (string) $response->effectiveUri(),
            fetcher: 'http',
        );
    }
}
