<?php

use App\Models\ScanRun;
use App\Models\ScanSource;
use App\Models\TelegramMessage;
use App\Models\Venue;
use App\Models\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.telegram.enabled', true);
    config()->set('services.telegram.token', 'TEST');
    config()->set('services.telegram.chat_id', '1');
    config()->set('services.telegram.stall_minutes', 30);
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
});

function makeRun(string $finishedAt): void
{
    $group = Group::firstOrCreate(['name' => 'G'], ['is_ours' => false]);
    $venue = Venue::firstOrCreate(['name' => 'V'], ['group_id' => $group->id, 'timezone' => 'Asia/Dubai']);
    $source = ScanSource::firstOrCreate(
        ['name' => 'S'],
        ['venue_id' => $venue->id, 'url' => 'https://x', 'strategy' => 'generic']
    );

    ScanRun::create([
        'scan_source_id' => $source->id,
        'status' => 'success',
        'started_at' => $finishedAt,
        'finished_at' => $finishedAt,
    ]);
}

it('alerts when scanning has stalled', function () {
    makeRun(now()->subMinutes(45)->toDateTimeString());

    $this->artisan('telegram:alerts')->assertSuccessful();

    expect(TelegramMessage::where('kind', 'alert_stalled')->where('status', 'sent')->exists())->toBeTrue();
});

it('does not alert when a scan succeeded recently', function () {
    makeRun(now()->subMinutes(5)->toDateTimeString());

    $this->artisan('telegram:alerts');

    expect(TelegramMessage::where('kind', 'alert_stalled')->exists())->toBeFalse();
});

it('does not alert on a fresh install with no scans', function () {
    $this->artisan('telegram:alerts');

    expect(TelegramMessage::where('kind', 'alert_stalled')->exists())->toBeFalse();
});
