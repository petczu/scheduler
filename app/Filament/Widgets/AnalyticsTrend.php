<?php

namespace App\Filament\Widgets;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Configurable trend for the Analytics page. Reads the page filters
 * (period, level, metric) and draws one line per venue or per room so you
 * can measure against individual players.
 */
class AnalyticsTrend extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Trend';

    protected int|string|array $columnSpan = 'full';

    private const OUR_COLORS = ['#22c55e', '#16a34a', '#4ade80', '#15803d', '#86efac', '#10b981'];

    private const COMPETITOR_COLORS = [
        '#60a5fa', '#f59e0b', '#ef4444', '#a78bfa', '#22d3ee', '#ec4899',
        '#f97316', '#14b8a6', '#eab308', '#8b5cf6', '#f43f5e', '#0ea5e9',
        '#84cc16', '#d946ef', '#fb7185', '#38bdf8',
    ];

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $period = (int) ($this->pageFilters['period'] ?? 30);
        $level = $this->pageFilters['level'] ?? 'venue';
        $metric = $this->pageFilters['metric'] ?? 'occupancy';

        $since = Carbon::now('Asia/Dubai')->subDays($period - 1)->toDateString();
        $byRoom = $level === 'room';

        $query = DB::table('daily_room_stats as d')
            ->join('rooms as r', 'r.id', '=', 'd.room_id')
            ->join('venues as v', 'v.id', '=', 'r.venue_id')
            ->join('groups as g', 'g.id', '=', 'v.group_id')
            ->where('r.is_active', true)
            ->where('r.under_maintenance', false)
            ->where('d.date', '>=', $since);

        if ($byRoom) {
            // One line per room; the entity is the room.
            $rows = $query
                ->groupBy('d.date', 'r.id', 'v.name', 'r.name', 'g.is_ours')
                ->selectRaw('d.date, r.id as entity_id, v.name as venue, r.name as room, g.is_ours,
                    SUM(d.sold_out) as sold, SUM(d.slots_total) as total')
                ->get();
        } else {
            // One line per venue; aggregate its rooms per day.
            $rows = $query
                ->groupBy('d.date', 'v.id', 'v.name', 'g.is_ours')
                ->selectRaw("d.date, v.id as entity_id, v.name as venue, '' as room, g.is_ours,
                    SUM(d.sold_out) as sold, SUM(d.slots_total) as total")
                ->get();
        }

        $dates = collect(range(0, $period - 1))
            ->map(fn ($i) => Carbon::parse($since, 'Asia/Dubai')->addDays($i)->toDateString());

        $entities = $rows
            ->unique('entity_id')
            ->sortBy([['is_ours', 'desc'], ['venue', 'asc'], ['room', 'asc']])
            ->values();

        $ourIdx = 0;
        $compIdx = 0;
        $datasets = $entities->map(function ($e) use ($rows, $dates, $metric, $byRoom, &$ourIdx, &$compIdx) {
            $color = (int) $e->is_ours === 1
                ? self::OUR_COLORS[$ourIdx++ % count(self::OUR_COLORS)]
                : self::COMPETITOR_COLORS[$compIdx++ % count(self::COMPETITOR_COLORS)];

            $data = $dates->map(function ($date) use ($rows, $e, $metric) {
                $row = $rows->first(
                    fn ($r) => (int) $r->entity_id === (int) $e->entity_id
                        && substr((string) $r->date, 0, 10) === $date
                );

                if (! $row || $row->total <= 0) {
                    return null;
                }

                return $metric === 'booked'
                    ? (int) $row->sold
                    : (int) round($row->sold * 100 / $row->total);
            })->all();

            return [
                'label' => $byRoom ? "{$e->venue} · {$e->room}" : $e->venue,
                'data' => $data,
                'borderColor' => $color,
                'backgroundColor' => $color,
                'spanGaps' => true,
                'tension' => 0.3,
            ];
        })->all();

        // Long periods: thin the x labels so they stay readable.
        $labelFormat = $period > 90 ? 'd M y' : 'd M';

        return [
            'datasets' => $datasets,
            'labels' => $dates->map(fn ($date) => Carbon::parse($date)->format($labelFormat))->all(),
        ];
    }
}
