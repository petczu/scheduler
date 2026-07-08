<?php

use App\Filament\Widgets\AnalyticsTrend;
use App\Filament\Widgets\MonthlyBreakdown;
use App\Models\DailyRoomStat;
use App\Models\Group;
use App\Models\Room;
use App\Models\User;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function analyticsTrend(array $filters): array
{
    $w = new AnalyticsTrend();
    $w->pageFilters = $filters;

    return (new ReflectionMethod(AnalyticsTrend::class, 'getData'))->invoke($w);
}

function seedTwoVenues(): array
{
    $ours = Group::create(['name' => 'Ours', 'is_ours' => true]);
    $comp = Group::create(['name' => 'Rivals', 'is_ours' => false]);
    $v1 = Venue::create(['group_id' => $ours->id, 'name' => 'No Way Out', 'timezone' => 'Asia/Dubai']);
    $v2 = Venue::create(['group_id' => $comp->id, 'name' => 'Game Over', 'timezone' => 'Asia/Dubai']);
    $r1 = Room::create(['venue_id' => $v1->id, 'name' => 'Orient Express']);
    $r2 = Room::create(['venue_id' => $v2->id, 'name' => 'Orient Express']);

    $date = CarbonImmutable::now('Asia/Dubai')->toDateString();
    DailyRoomStat::create(['room_id' => $r1->id, 'date' => $date, 'slots_total' => 10, 'sold_out' => 5, 'occupancy' => 50, 'am_total' => 6, 'am_sold_out' => 4, 'pm_total' => 4, 'pm_sold_out' => 1]);
    DailyRoomStat::create(['room_id' => $r2->id, 'date' => $date, 'slots_total' => 10, 'sold_out' => 2, 'occupancy' => 20, 'am_total' => 6, 'am_sold_out' => 1, 'pm_total' => 4, 'pm_sold_out' => 1]);

    return compact('v1', 'v2', 'r1', 'r2', 'date');
}

it('renders the analytics page', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin/analytics')
        ->assertOk();
});

it('plots one line per venue at venue level', function () {
    seedTwoVenues();

    $data = analyticsTrend(['period' => '7', 'level' => 'venue', 'metric' => 'occupancy']);
    $labels = collect($data['datasets'])->pluck('label');

    expect($labels)->toContain('No Way Out', 'Game Over')
        ->and($labels)->not->toContain('No Way Out · Orient Express');
});

it('plots one line per room at room level', function () {
    seedTwoVenues();

    $data = analyticsTrend(['period' => '7', 'level' => 'room', 'metric' => 'occupancy']);
    $labels = collect($data['datasets'])->pluck('label');

    expect($labels)->toContain('No Way Out · Orient Express', 'Game Over · Orient Express');
});

it('plots booked-slot counts when the metric is booked', function () {
    seedTwoVenues();

    $data = analyticsTrend(['period' => '7', 'level' => 'venue', 'metric' => 'booked']);
    $ds = collect($data['datasets'])->firstWhere('label', 'No Way Out');
    $idx = array_search(CarbonImmutable::now('Asia/Dubai')->format('d M'), $data['labels'], true);

    expect((int) $ds['data'][$idx])->toBe(5);
});

it('builds a monthly breakdown with morning/evening split', function () {
    seedTwoVenues();

    $w = new MonthlyBreakdown();
    $w->pageFilters = ['period' => '30'];
    $rows = $w->getRows();

    expect($rows)->not->toBeEmpty()
        ->and($rows[0]['ours']['occupancy'])->toBe(50)
        ->and($rows[0]['ours']['am'])->toBe(67)   // 4/6
        ->and($rows[0]['competitors']['occupancy'])->toBe(20);
});
