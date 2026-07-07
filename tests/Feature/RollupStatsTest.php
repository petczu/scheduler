<?php

use App\Models\DailyRoomStat;
use App\Models\Group;
use App\Models\Room;
use App\Models\ScanRun;
use App\Models\ScanSource;
use App\Models\SlotSnapshot;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function rollupRoom(): Room
{
    $group = Group::create(['name' => 'Competitors', 'is_ours' => false]);
    $venue = Venue::create(['group_id' => $group->id, 'name' => 'V', 'timezone' => 'Asia/Dubai']);

    return Room::create(['venue_id' => $venue->id, 'name' => 'Room']);
}

function snapshot(Room $room, string $slotAt, string $status, string $scannedAt): void
{
    static $run = null;
    $run ??= ScanRun::create([
        'scan_source_id' => ScanSource::create([
            'venue_id' => $room->venue_id, 'name' => 'S', 'url' => 'https://x', 'strategy' => 'generic',
        ])->id,
        'status' => 'success',
        'started_at' => now(),
    ]);

    SlotSnapshot::create([
        'room_id' => $room->id,
        'scan_run_id' => $run->id,
        'slot_at' => $slotAt,
        'status' => $status,
        'scanned_at' => $scannedAt,
    ]);
}

it('rolls a day of snapshots into one compact stat row', function () {
    $room = rollupRoom();
    $day = '2026-07-01';

    // 10:00 morning slot ends sold out; 20:00 evening slot available.
    snapshot($room, "{$day} 10:00:00", 'available', "{$day} 08:00:00");
    snapshot($room, "{$day} 10:00:00", 'sold_out', "{$day} 09:00:00");
    snapshot($room, "{$day} 20:00:00", 'available', "{$day} 09:00:00");

    $this->artisan('stats:rollup', ['--date' => $day])->assertSuccessful();

    $stat = DailyRoomStat::where('room_id', $room->id)->where('date', $day)->first();

    expect($stat)->not->toBeNull()
        ->and($stat->slots_total)->toBe(2)
        ->and($stat->sold_out)->toBe(1)
        ->and($stat->occupancy)->toBe(50)
        ->and($stat->am_total)->toBe(1)
        ->and($stat->am_sold_out)->toBe(1)
        ->and($stat->pm_total)->toBe(1)
        ->and($stat->pm_sold_out)->toBe(0);
});

it('is idempotent (re-running updates, not duplicates)', function () {
    $room = rollupRoom();
    $day = '2026-07-01';
    snapshot($room, "{$day} 12:00:00", 'sold_out', "{$day} 09:00:00");

    $this->artisan('stats:rollup', ['--date' => $day]);
    $this->artisan('stats:rollup', ['--date' => $day]);

    expect(DailyRoomStat::where('room_id', $room->id)->count())->toBe(1);
});

it('records a released (fake booking) count', function () {
    $room = rollupRoom();
    $day = '2026-07-01';
    // sold out then available again on a slot -> released.
    snapshot($room, "{$day} 15:00:00", 'sold_out', "{$day} 08:00:00");
    snapshot($room, "{$day} 15:00:00", 'available', "{$day} 09:00:00");

    $this->artisan('stats:rollup', ['--date' => $day]);

    expect(DailyRoomStat::first()->released)->toBe(1);
});
