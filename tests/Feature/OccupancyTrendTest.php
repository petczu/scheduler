<?php

use App\Filament\Widgets\OccupancyTrend;
use App\Models\DailyRoomStat;
use App\Models\Group;
use App\Models\Room;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function trendData(string $metric = 'occupancy'): array
{
    $widget = new OccupancyTrend();
    $widget->filter = $metric;

    $method = new ReflectionMethod(OccupancyTrend::class, 'getData');

    return $method->invoke($widget);
}

function todayIndex(array $data): int
{
    return array_search(CarbonImmutable::now('Asia/Dubai')->format('d M'), $data['labels'], true);
}

function venueDataset(array $data, string $label): ?array
{
    foreach ($data['datasets'] as $ds) {
        if ($ds['label'] === $label) {
            return $ds;
        }
    }

    return null;
}

it('draws one line per venue', function () {
    $ours = Group::create(['name' => 'Ours', 'is_ours' => true]);
    $comp = Group::create(['name' => 'Rivals', 'is_ours' => false]);
    $v1 = Venue::create(['group_id' => $ours->id, 'name' => 'No Way Out', 'timezone' => 'Asia/Dubai']);
    $v2 = Venue::create(['group_id' => $comp->id, 'name' => 'Game Over', 'timezone' => 'Asia/Dubai']);
    $r1 = Room::create(['venue_id' => $v1->id, 'name' => 'A']);
    $r2 = Room::create(['venue_id' => $v2->id, 'name' => 'B']);

    $date = CarbonImmutable::now('Asia/Dubai')->toDateString();
    DailyRoomStat::create(['room_id' => $r1->id, 'date' => $date, 'slots_total' => 10, 'sold_out' => 5, 'occupancy' => 50]);
    DailyRoomStat::create(['room_id' => $r2->id, 'date' => $date, 'slots_total' => 10, 'sold_out' => 3, 'occupancy' => 30]);

    $data = trendData();

    expect(collect($data['datasets'])->pluck('label')->all())->toContain('No Way Out', 'Game Over');
});

it('excludes maintenance rooms from a venue line', function () {
    $group = Group::create(['name' => 'Ours', 'is_ours' => true]);
    $venue = Venue::create(['group_id' => $group->id, 'name' => 'No Way Out', 'timezone' => 'Asia/Dubai']);
    $open = Room::create(['venue_id' => $venue->id, 'name' => 'Open']);
    $closed = Room::create(['venue_id' => $venue->id, 'name' => 'Psycho', 'under_maintenance' => true]);

    $date = CarbonImmutable::now('Asia/Dubai')->toDateString();
    DailyRoomStat::create(['room_id' => $open->id, 'date' => $date, 'slots_total' => 10, 'sold_out' => 4, 'occupancy' => 40]);
    DailyRoomStat::create(['room_id' => $closed->id, 'date' => $date, 'slots_total' => 10, 'sold_out' => 10, 'occupancy' => 100]);

    $data = trendData();
    $ds = venueDataset($data, 'No Way Out');

    // Only the open room (40%) — not the average with the maintenance room.
    expect((int) $ds['data'][todayIndex($data)])->toBe(40);
});

it('weights occupancy by slot count within a venue', function () {
    $group = Group::create(['name' => 'Ours', 'is_ours' => true]);
    $venue = Venue::create(['group_id' => $group->id, 'name' => 'No Way Out', 'timezone' => 'Asia/Dubai']);
    $small = Room::create(['venue_id' => $venue->id, 'name' => 'Small']);
    $big = Room::create(['venue_id' => $venue->id, 'name' => 'Big']);

    $date = CarbonImmutable::now('Asia/Dubai')->toDateString();
    DailyRoomStat::create(['room_id' => $small->id, 'date' => $date, 'slots_total' => 2, 'sold_out' => 2, 'occupancy' => 100]);
    DailyRoomStat::create(['room_id' => $big->id, 'date' => $date, 'slots_total' => 18, 'sold_out' => 0, 'occupancy' => 0]);

    $data = trendData();
    $ds = venueDataset($data, 'No Way Out');

    // Weighted 2/20 = 10%, not the per-room average of 50%.
    expect((int) $ds['data'][todayIndex($data)])->toBe(10);
});

it('can plot the booked-slots count instead of percent', function () {
    $group = Group::create(['name' => 'Ours', 'is_ours' => true]);
    $venue = Venue::create(['group_id' => $group->id, 'name' => 'No Way Out', 'timezone' => 'Asia/Dubai']);
    $room = Room::create(['venue_id' => $venue->id, 'name' => 'A']);

    $date = CarbonImmutable::now('Asia/Dubai')->toDateString();
    DailyRoomStat::create(['room_id' => $room->id, 'date' => $date, 'slots_total' => 10, 'sold_out' => 7, 'occupancy' => 70]);

    $data = trendData('booked');
    $ds = venueDataset($data, 'No Way Out');

    expect((int) $ds['data'][todayIndex($data)])->toBe(7);
});
