<?php

use App\Models\Group;
use App\Models\Room;
use App\Models\ScanRun;
use App\Models\ScanSource;
use App\Models\SlotSnapshot;
use App\Models\TelegramMessage;
use App\Models\Venue;
use App\Telegram\Notifier;
use App\Telegram\OccupancyReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function enableTelegram(): void
{
    config()->set('services.telegram.enabled', true);
    config()->set('services.telegram.token', 'TEST');
    config()->set('services.telegram.chat_id', '123');
}

function competitorRoomWithSoldOut(int $soldOut, int $total): Room
{
    $group = Group::create(['name' => 'Competitors', 'is_ours' => false]);
    $venue = Venue::create(['group_id' => $group->id, 'name' => 'Rival', 'timezone' => 'Asia/Dubai']);
    $room = Room::create(['venue_id' => $venue->id, 'name' => 'Room X']);
    $run = ScanRun::create(['scan_source_id' => ScanSource::create([
        'venue_id' => $venue->id, 'name' => 'S', 'url' => 'https://x', 'strategy' => 'generic',
    ])->id, 'status' => 'success', 'started_at' => now()]);

    $today = now('Asia/Dubai')->startOfDay();
    for ($i = 0; $i < $total; $i++) {
        SlotSnapshot::create([
            'room_id' => $room->id,
            'scan_run_id' => $run->id,
            'slot_at' => $today->copy()->setTime(10 + $i, 0),
            'status' => $i < $soldOut ? 'sold_out' : 'available',
            'scanned_at' => now(),
        ]);
    }

    return $room;
}

it('sends a message and logs it as sent', function () {
    enableTelegram();
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

    $sent = app(Notifier::class)->send('test', 'hello');

    expect($sent)->toBeTrue()
        ->and(TelegramMessage::where('status', 'sent')->count())->toBe(1);

    Http::assertSent(fn ($r) => str_contains($r->url(), '/sendMessage'));
});

it('skips sending when Telegram is not configured', function () {
    config()->set('services.telegram.enabled', false);
    Http::fake();

    $sent = app(Notifier::class)->send('test', 'hello');

    expect($sent)->toBeFalse()
        ->and(TelegramMessage::where('status', 'skipped')->count())->toBe(1);

    Http::assertNothingSent();
});

it('deduplicates messages by signature', function () {
    enableTelegram();
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

    app(Notifier::class)->send('alert_sold_out', 'a', 'sig-1');
    app(Notifier::class)->send('alert_sold_out', 'a', 'sig-1');

    expect(TelegramMessage::count())->toBe(1);
});

it('sends a targeted preview to specific chats', function () {
    enableTelegram();
    config()->set('services.telegram.chat_id', null);
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

    $ok = app(Notifier::class)->sendToChats(['777'], 'test', 'preview');

    expect($ok)->toBeTrue();
    Http::assertSentCount(1);
    Http::assertSent(fn ($r) => $r['chat_id'] === '777' && $r['text'] === 'preview');
    expect(TelegramMessage::where('kind', 'test')->where('status', 'sent')->count())->toBe(1);
});

it('skips a targeted preview with no recipients', function () {
    enableTelegram();
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

    $ok = app(Notifier::class)->sendToChats([], 'test', 'preview');

    expect($ok)->toBeFalse();
    Http::assertNothingSent();
});

it('detects a sold-out competitor alert above the threshold', function () {
    config()->set('services.telegram.sold_out_threshold', 80);

    competitorRoomWithSoldOut(9, 10); // 90%

    $alerts = app(OccupancyReport::class)->detectAlerts();

    expect(collect($alerts)->pluck('kind'))->toContain('alert_sold_out');
});

it('builds a digest naming ours and competitors', function () {
    competitorRoomWithSoldOut(5, 10);

    $text = app(OccupancyReport::class)->digest('morning');

    expect($text)->toContain('Morning digest')
        ->toContain('Our projects')
        ->toContain('Competitors')
        ->toContain('Rival');
});
