<?php

use App\Filament\Widgets\OccupancyTrend;
use App\Models\DailyRoomStat;
use App\Models\Group;
use App\Models\Room;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function trendData(): array
{
    $method = new ReflectionMethod(OccupancyTrend::class, 'getData');
    $method->setAccessible(true);

    return $method->invoke(new OccupancyTrend());
}

it('excludes maintenance rooms from the occupancy trend', function () {
    $group = Group::create(['name' => 'Ours', 'is_ours' => true]);
    $venue = Venue::create(['group_id' => $group->id, 'name' => 'V', 'timezone' => 'Asia/Dubai']);

    $open = Room::create(['venue_id' => $venue->id, 'name' => 'Open']);
    $closed = Room::create(['venue_id' => $venue->id, 'name' => 'Psycho', 'under_maintenance' => true]);

    $date = CarbonImmutable::now('Asia/Dubai')->toDateString();
    DailyRoomStat::create(['room_id' => $open->id, 'date' => $date, 'slots_total' => 10, 'sold_out' => 4, 'occupancy' => 40]);
    DailyRoomStat::create(['room_id' => $closed->id, 'date' => $date, 'slots_total' => 10, 'sold_out' => 10, 'occupancy' => 100]);

    $data = trendData();

    // "Our projects" is the first dataset; find today's index by label.
    $labels = $data['labels'];
    $todayLabel = CarbonImmutable::now('Asia/Dubai')->format('d M');
    $idx = array_search($todayLabel, $labels, true);

    // Only the open room (40%) counts — not the average of 40 and 100 (70%).
    expect($data['datasets'][0]['label'])->toBe('Our projects')
        ->and((int) $data['datasets'][0]['data'][$idx])->toBe(40);
});

it('weights occupancy by slot count, not per-room average', function () {
    $group = Group::create(['name' => 'Ours', 'is_ours' => true]);
    $venue = Venue::create(['group_id' => $group->id, 'name' => 'V', 'timezone' => 'Asia/Dubai']);

    $small = Room::create(['venue_id' => $venue->id, 'name' => 'Small']);
    $big = Room::create(['venue_id' => $venue->id, 'name' => 'Big']);

    $date = CarbonImmutable::now('Asia/Dubai')->toDateString();
    // Small: 2/2 = 100%; Big: 0/18 = 0%. Simple avg = 50%, weighted = 2/20 = 10%.
    DailyRoomStat::create(['room_id' => $small->id, 'date' => $date, 'slots_total' => 2, 'sold_out' => 2, 'occupancy' => 100]);
    DailyRoomStat::create(['room_id' => $big->id, 'date' => $date, 'slots_total' => 18, 'sold_out' => 0, 'occupancy' => 0]);

    $data = trendData();
    $idx = array_search(CarbonImmutable::now('Asia/Dubai')->format('d M'), $data['labels'], true);

    expect((int) $data['datasets'][0]['data'][$idx])->toBe(10);
});
