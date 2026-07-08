<?php

namespace App\Filament\Widgets;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Filament\Widgets\ChartWidget;

/**
 * Trend over the last 30 days with one line per venue (ours + each competitor)
 * so you can compare against individual players. A filter switches the metric
 * between occupancy % and the absolute number of booked slots.
 */
class OccupancyTrend extends ChartWidget
{
    protected ?string $heading = 'Occupancy trend by venue (last 30 days)';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public ?string $filter = 'occupancy';

    /** Greens for our venues so they read as a group. */
    private const OUR_COLORS = ['#22c55e', '#16a34a', '#4ade80', '#15803d', '#86efac'];

    /** Distinct colors for competitors. */
    private const COMPETITOR_COLORS = [
        '#60a5fa', '#f59e0b', '#ef4444', '#a78bfa', '#22d3ee',
        '#ec4899', '#f97316', '#14b8a6', '#eab308', '#8b5cf6',
        '#f43f5e', '#0ea5e9', '#84cc16', '#d946ef',
    ];

    protected function getType(): string
    {
        return 'line';
    }

    protected function getFilters(): ?array
    {
        return [
            'occupancy' => 'Occupancy %',
            'booked' => 'Booked slots',
        ];
    }

    protected function getData(): array
    {
        $metric = $this->filter ?? 'occupancy';
        $since = Carbon::now('Asia/Dubai')->subDays(29)->toDateString();

        $rows = DB::table('daily_room_stats as d')
            ->join('rooms as r', 'r.id', '=', 'd.room_id')
            ->join('venues as v', 'v.id', '=', 'r.venue_id')
            ->join('groups as g', 'g.id', '=', 'v.group_id')
            ->where('r.is_active', true)
            ->where('r.under_maintenance', false)
            ->where('d.date', '>=', $since)
            ->groupBy('d.date', 'v.id', 'v.name', 'g.is_ours')
            ->selectRaw('d.date, v.id as venue_id, v.name as venue, g.is_ours,
                SUM(d.sold_out) as sold, SUM(d.slots_total) as total')
            ->get();

        $dates = collect(range(0, 29))
            ->map(fn ($i) => Carbon::parse($since, 'Asia/Dubai')->addDays($i)->toDateString());

        // One venue per line, ours first then by name.
        $venues = $rows
            ->unique('venue_id')
            ->sortBy([['is_ours', 'desc'], ['venue', 'asc']])
            ->values();

        $ourIdx = 0;
        $compIdx = 0;
        $datasets = $venues->map(function ($v) use ($rows, $dates, $metric, &$ourIdx, &$compIdx) {
            $color = (int) $v->is_ours === 1
                ? self::OUR_COLORS[$ourIdx++ % count(self::OUR_COLORS)]
                : self::COMPETITOR_COLORS[$compIdx++ % count(self::COMPETITOR_COLORS)];

            $data = $dates->map(function ($date) use ($rows, $v, $metric) {
                $row = $rows->first(
                    fn ($r) => (int) $r->venue_id === (int) $v->venue_id
                        && substr((string) $r->date, 0, 10) === $date
                );

                if (! $row || $row->total <= 0) {
                    return null; // gap on days with no data
                }

                return $metric === 'booked'
                    ? (int) $row->sold
                    : (int) round($row->sold * 100 / $row->total);
            })->all();

            return [
                'label' => $v->venue,
                'data' => $data,
                'borderColor' => $color,
                'backgroundColor' => $color,
                'spanGaps' => true,
                'tension' => 0.3,
            ];
        })->all();

        return [
            'datasets' => $datasets,
            'labels' => $dates->map(fn ($date) => Carbon::parse($date)->format('d M'))->all(),
        ];
    }
}
