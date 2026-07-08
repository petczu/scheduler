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

    protected static ?int $sort = 1;

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
            // Exclude rooms currently active-off or on maintenance, matching
            // Room::counted() used elsewhere — their downtime isn't demand.
            ->where('r.is_active', true)
            ->where('r.under_maintenance', false)
            ->groupBy('d.date', 'g.is_ours')
            // Weighted occupancy: total booked slots / total slots for the
            // group (matches the digest's per-venue math, not an average of
            // per-room percentages).
            ->selectRaw('d.date, g.is_ours, ROUND(SUM(d.sold_out) * 100.0 / NULLIF(SUM(d.slots_total), 0)) as occ')
            ->get();

        $dates = collect(range(0, 29))
            ->map(fn ($i) => Carbon::parse($since, 'Asia/Dubai')->addDays($i)->toDateString());

        // $r->date may come back as 'Y-m-d' (MySQL DATE) or 'Y-m-d H:i:s'
        // depending on the driver; compare on the date part only.
        $onDate = fn ($r, $date, $ours) => substr((string) $r->date, 0, 10) === $date
            && (int) $r->is_ours === $ours;

        $ours = $dates->map(fn ($date) => optional($rows->first(fn ($r) => $onDate($r, $date, 1)))->occ);
        $competitors = $dates->map(fn ($date) => optional($rows->first(fn ($r) => $onDate($r, $date, 0)))->occ);

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
