<?php

use App\Models\Group;
use App\Models\Room;
use App\Models\ScanRun;
use App\Models\ScanSource;
use App\Models\SlotSnapshot;
use App\Models\Venue;
use App\Scraping\ScanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function makeSource(array $attributes = []): ScanSource
{
    $group = Group::create(['name' => 'Competitors', 'is_ours' => false]);
    $venue = Venue::create([
        'group_id' => $group->id,
        'name' => 'Test Venue',
        'timezone' => 'Asia/Dubai',
    ]);

    return ScanSource::create(array_merge([
        'venue_id' => $venue->id,
        'name' => 'Test Source',
        'url' => 'https://example.com/booking',
        'strategy' => 'generic',
        'fetcher' => 'http',
        'parse_mode' => 'detect_busy',
        'parser_config' => [
            'slot_selector' => '.time-slot',
            'date_attr' => 'data-date',
            'busy_matchers' => [
                ['type' => 'text', 'value' => 'SOLD OUT'],
                ['type' => 'class', 'value' => 'disabled'],
            ],
        ],
    ], $attributes));
}

it('scans a generic page and stores slot snapshots', function () {
    Storage::fake('local');

    $today = now('Asia/Dubai')->format('Y-m-d');

    Http::fake([
        'example.com/*' => Http::response(<<<HTML
            <div class="slots">
                <a class="time-slot" data-date="{$today}">10:00</a>
                <a class="time-slot" data-date="{$today}">11:30 SOLD OUT</a>
                <a class="time-slot disabled" data-date="{$today}">1:00 pm</a>
                <a class="time-slot" data-date="{$today}">14:30</a>
            </div>
        HTML),
    ]);

    $run = app(ScanService::class)->scan(makeSource());

    expect($run->status)->toBe('success')
        ->and($run->slots_found)->toBe(4)
        ->and($run->rooms_found)->toBe(1)
        // Single-room source auto-creates its room on first scan.
        ->and(Room::count())->toBe(1);

    $statuses = SlotSnapshot::orderBy('slot_at')->pluck('status', 'slot_at')->all();

    expect($statuses["{$today} 10:00:00"])->toBe('available')
        ->and($statuses["{$today} 11:30:00"])->toBe('sold_out')
        ->and($statuses["{$today} 13:00:00"])->toBe('sold_out')
        ->and($statuses["{$today} 14:30:00"])->toBe('available');
});

it('groups slots per card and auto-creates rooms on multi-room pages', function () {
    Storage::fake('local');

    $today = now('Asia/Dubai')->format('Y-m-d');

    Http::fake([
        'example.com/*' => Http::response(<<<HTML
            <div class="room-card">
                <h3 class="room-title">Zombie Lab</h3>
                <a class="time-slot" data-date="{$today}">10:00</a>
                <a class="time-slot disabled" data-date="{$today}">12:00</a>
            </div>
            <div class="room-card">
                <h3 class="room-title">Prison Break</h3>
                <a class="time-slot" data-date="{$today}">10:00</a>
            </div>
        HTML),
    ]);

    $source = makeSource([
        'parser_config' => [
            'card_selector' => '.room-card',
            'card_title_selector' => '.room-title',
            'slot_selector' => '.time-slot',
            'date_attr' => 'data-date',
            'busy_matchers' => [['type' => 'class', 'value' => 'disabled']],
        ],
    ]);

    $run = app(ScanService::class)->scan($source);

    expect($run->status)->toBe('success')
        ->and($run->rooms_found)->toBe(2)
        ->and(Room::pluck('name')->sort()->values()->all())->toBe(['Prison Break', 'Zombie Lab']);

    $zombie = Room::where('name', 'Zombie Lab')->first();
    expect($zombie->slotSnapshots()->count())->toBe(2)
        ->and($zombie->slotSnapshots()->where('status', 'sold_out')->count())->toBe(1);

    // A rescan maps to the existing rooms instead of duplicating them.
    app(ScanService::class)->scan($source->fresh());
    expect(Room::count())->toBe(2);
});

it('unwraps JSON responses, resolves {today} and reads time from an attribute', function () {
    Storage::fake('local');

    $today = now('Asia/Dubai')->format('Y-m-d');

    Http::fake(function ($request) use ($today) {
        expect($request->url())->toContain("date={$today}")
            ->and($request->hasHeader('X-Requested-With', 'XMLHttpRequest'))->toBeTrue();

        return Http::response(json_encode([
            'data' => [
                'schedule_partial' => <<<HTML
                    <div class="day__block">
                        <a class="day__room-name">Psycho</a>
                        <div class="booking__slot-btn " data-date="{$today}" data-time="11:45"></div>
                        <div class="booking__slot-btn disabled " data-date="{$today}" data-time="13:00"></div>
                    </div>
                HTML,
            ],
        ]));
    });

    $source = makeSource([
        'url' => 'https://example.com/schedule-partial/?date={today}',
        'parser_config' => [
            'json_html_path' => 'data.schedule_partial',
            'card_selector' => '.day__block',
            'card_title_selector' => '.day__room-name',
            'slot_selector' => '.booking__slot-btn',
            'date_attr' => 'data-date',
            'time_attr' => 'data-time',
            'busy_matchers' => [['type' => 'class', 'value' => 'disabled']],
            'http_headers' => ['X-Requested-With' => 'XMLHttpRequest'],
        ],
    ]);

    $run = app(ScanService::class)->scan($source);

    expect($run->status)->toBe('success')
        ->and($run->slots_found)->toBe(2)
        ->and(Room::first()->name)->toBe('Psycho');

    $statuses = SlotSnapshot::orderBy('slot_at')->pluck('status')->all();
    expect($statuses)->toBe(['available', 'sold_out']);
});

it('supports detect_free mode', function () {
    Storage::fake('local');

    $today = now('Asia/Dubai')->format('Y-m-d');

    Http::fake([
        'example.com/*' => Http::response(<<<HTML
            <div>
                <span class="time-slot open" data-date="{$today}">18:00</span>
                <span class="time-slot" data-date="{$today}">19:30</span>
            </div>
        HTML),
    ]);

    $source = makeSource([
        'parse_mode' => 'detect_free',
        'parser_config' => [
            'slot_selector' => '.time-slot',
            'date_attr' => 'data-date',
            'free_matchers' => [['type' => 'class', 'value' => 'open']],
        ],
    ]);

    app(ScanService::class)->scan($source);

    expect(SlotSnapshot::where('status', 'available')->count())->toBe(1)
        ->and(SlotSnapshot::where('status', 'sold_out')->count())->toBe(1);
});

it('fails the run when the target returns a block page', function () {
    Storage::fake('local');
    Http::fake(['example.com/*' => Http::response('Access from unauthorized IP address detected.')]);

    $run = app(ScanService::class)->scan(makeSource());

    expect($run->status)->toBe('failed')
        ->and($run->error)->toContain('Blocked');
});

it('marks the run as failed when the fetch errors out', function () {
    Storage::fake('local');
    Http::fake(['example.com/*' => Http::response('nope', 503)]);

    $run = app(ScanService::class)->scan(makeSource());

    expect($run->status)->toBe('failed')
        ->and($run->error)->not->toBeNull()
        ->and(SlotSnapshot::count())->toBe(0);
});

it('infers a booking when a future slot disappears from an available-only source', function () {
    Storage::fake('local');

    $today = now('Asia/Dubai')->format('Y-m-d');

    // A single stub whose response changes between scans.
    $times = ['20:00', '22:00'];
    Http::fake(['example.com/*' => function () use (&$times) {
        return Http::response(json_encode([
            'availableHours' => array_map(fn ($t) => ['start' => $t], $times),
        ]));
    }]);

    $source = makeSource([
        'strategy' => 'json',
        'available_only' => true,
        'parser_config' => [
            'slots_path' => 'availableHours',
            'time_key' => 'start',
        ],
    ]);

    // Freeze "now" before both slots so they are in the future.
    $this->travelTo(now('Asia/Dubai')->setTime(9, 0));
    app(ScanService::class)->scan($source);

    // Second scan an hour later: 22:00 vanished (booked), 20:00 remains.
    $this->travel(1)->hours();
    $times = ['20:00'];
    app(ScanService::class)->scan($source->fresh());

    $room = Room::first();
    $stats = $room->todayStats();

    expect($stats['total'])->toBe(2)            // both times known
        ->and($stats['sold_out'])->toBe(1)      // 22:00 inferred as booked
        ->and(SlotSnapshot::where('room_id', $room->id)
            ->where('slot_at', "{$today} 22:00:00")
            ->where('status', 'sold_out')->exists())->toBeTrue();

    $this->travelBack();
});

it('does not infer a booking when the disappeared slot is already in the past', function () {
    Storage::fake('local');

    $source = makeSource([
        'strategy' => 'json',
        'available_only' => true,
        'parser_config' => ['slots_path' => 'availableHours', 'time_key' => 'start'],
    ]);

    $times = ['10:00'];
    Http::fake(['example.com/*' => function () use (&$times) {
        return Http::response(json_encode([
            'availableHours' => array_map(fn ($t) => ['start' => $t], $times),
        ]));
    }]);

    // First scan at 09:00 sees a 10:00 slot.
    $this->travelTo(now('Asia/Dubai')->setTime(9, 0));
    app(ScanService::class)->scan($source);

    // Second scan at 11:00: 10:00 is gone, but it is now in the PAST.
    $this->travelTo(now('Asia/Dubai')->setTime(11, 0));
    $times = [];
    app(ScanService::class)->scan($source->fresh());

    expect(SlotSnapshot::where('status', 'sold_out')->count())->toBe(0);

    $this->travelBack();
});

it('detects released slots (fake bookings) across multiple scans', function () {
    Storage::fake('local');

    // Freeze at a fixed morning time so a +1h travel can't cross midnight.
    $this->travelTo(now('Asia/Dubai')->setTime(9, 0));

    $source = makeSource();
    $room = Room::create([
        'venue_id' => $source->venue_id,
        'scan_source_id' => $source->id,
        'name' => 'Test Room',
    ]);
    $today = now('Asia/Dubai')->format('Y-m-d');

    $makeRun = function (string $status) use ($source, $room, $today) {
        $run = ScanRun::create(['scan_source_id' => $source->id, 'status' => 'success', 'started_at' => now()]);
        SlotSnapshot::create([
            'room_id' => $room->id,
            'scan_run_id' => $run->id,
            'slot_at' => "{$today} 20:00:00",
            'status' => $status,
            'scanned_at' => now(),
        ]);
    };

    $makeRun('sold_out');
    $this->travel(1)->hours();
    $makeRun('available');

    $stats = $room->fresh()->todayStats();

    expect($stats['total'])->toBe(1)
        ->and($stats['sold_out'])->toBe(0)
        ->and($stats['released'])->toBe(1);

    $this->travelBack();
});
