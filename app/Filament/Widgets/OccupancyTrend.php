<?php

namespace App\Filament\Widgets;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Filament\Widgets\ChartWidget;

/**
 * Occupancy trend over the last N days from the compact daily_room_stats
 * rollup — ours vs competitors. Cheap to render even with a year of history.
 */
class OccupancyTrend extends ChartWidget
{
    protected ?string $heading = 'Occupancy trend (last 30 days)';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $since = Carbon::now('Asia/Dubai')->subDays(29)->toDateString();

        // Average occupancy per day, split by our group vs competitors.
        $rows = DB::table('daily_room_stats as d')
            ->join('rooms as r', 'r.id', '=', 'd.room_id')
            ->join('venues as v', 'v.id', '=', 'r.venue_id')
            ->join('groups as g', 'g.id', '=', 'v.group_id')
            ->where('d.date', '>=', $since)
            ->whereNotNull('d.occupancy')
            ->groupBy('d.date', 'g.is_ours')
            ->selectRaw('d.date, g.is_ours, ROUND(AVG(d.occupancy)) as occ')
            ->get();

        $dates = collect(range(0, 29))
            ->map(fn ($i) => Carbon::parse($since, 'Asia/Dubai')->addDays($i)->toDateString());

        $ours = $dates->map(fn ($date) => optional($rows->first(
            fn ($r) => $r->date === $date && (int) $r->is_ours === 1
        ))->occ);

        $competitors = $dates->map(fn ($date) => optional($rows->first(
            fn ($r) => $r->date === $date && (int) $r->is_ours === 0
        ))->occ);

        return [
            'datasets' => [
                [
                    'label' => 'Our projects',
                    'data' => $ours->all(),
                    'borderColor' => '#22c55e',
                    'backgroundColor' => 'rgba(34,197,94,0.1)',
                ],
                [
                    'label' => 'Competitors',
                    'data' => $competitors->all(),
                    'borderColor' => '#94a3b8',
                    'backgroundColor' => 'rgba(148,163,184,0.1)',
                ],
            ],
            'labels' => $dates->map(fn ($date) => Carbon::parse($date)->format('d M'))->all(),
        ];
    }
}
