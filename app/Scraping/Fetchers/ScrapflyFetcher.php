<?php

namespace App\Scraping\Fetchers;

use App\Models\ScanSource;
use App\Scraping\FetchResult;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Fetch through the Scrapfly Scrape API. Used for sites behind anti-bot
 * protection (Bookeo etc.). Credit cost depends on enabled options:
 * datacenter=1, +5 render_js, residential ~25 (total ~30 with rendering).
 *
 * @see https://scrapfly.io/docs/scrape-api/getting-started
 */
class ScrapflyFetcher implements Fetcher
{
    public function fetch(ScanSource $source): FetchResult
    {
        $key = config('services.scrapfly.key');

        if (blank($key)) {
            throw new RuntimeException('SCRAPFLY_KEY is not configured (.env).');
        }

        $params = [
            'key' => $key,
            'url' => $source->resolvedUrl(),
            'country' => config('services.scrapfly.country', 'ae'),
            'asp' => $source->anti_bot ? 'true' : 'false',
            'render_js' => $source->render_js ? 'true' : 'false',
        ];

        if ($source->anti_bot) {
            // Bookeo (and similar) blocklist datacenter IPs outright, and ASP
            // does not always upgrade the pool on its own — force residential.
            $params['proxy_pool'] = 'public_residential_pool';
        }

        if ($source->render_js) {
            // Give SPA calendars time to load their slots.
            $params['rendering_wait'] = 3000;
        }

        $response = Http::timeout(120)
            ->get('https://api.scrapfly.io/scrape', $params)
            ->throw();

        $json = $response->json();
        $result = $json['result'] ?? [];

        if (blank($result['content'] ?? null)) {
            throw new RuntimeException('Scrapfly returned empty content: '.substr($response->body(), 0, 500));
        }

        return new FetchResult(
            html: $result['content'],
            finalUrl: $result['url'] ?? $source->url,
            fetcher: 'scrapfly',
            creditsCost: data_get($json, 'context.cost.total')
                ?? data_get($json, 'result.cost.total'),
        );
    }
}
