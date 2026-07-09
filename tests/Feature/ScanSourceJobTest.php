<?php

use App\Jobs\ScanSourceJob;
use App\Models\ScanSource;
use Illuminate\Queue\Middleware\WithoutOverlapping;

it('serializes Scrapfly jobs but not HTTP jobs', function () {
    $scrapfly = new ScanSourceJob(new ScanSource(['fetcher' => 'scrapfly']));
    $http = new ScanSourceJob(new ScanSource(['fetcher' => 'http']));

    expect($scrapfly->middleware())->toHaveCount(1)
        ->and($scrapfly->middleware()[0])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($http->middleware())->toBe([]);
});

it('is unique per source', function () {
    $source = new ScanSource(['fetcher' => 'http']);
    $source->id = 42;

    expect((new ScanSourceJob($source))->uniqueId())->toBe('scan-source-42');
});
