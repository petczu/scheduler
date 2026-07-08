<?php

namespace App\Filament\Widgets;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Filament\Widgets\Widget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Month-by-month occupancy for our projects vs competitors, with a
 * morning/evening split. Reads the Analytics page period filter.
 */
class MonthlyBreakdown extends Widget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected string $view = 'filament.widgets.monthly-breakdown';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRows(): array
    {
        $period = (int) ($this->pageFilters['period'] ?? 30);
        $since = Carbon::now('Asia/Dubai')->subDays($period - 1)->toDateString();

        $rows = DB::table('daily_room_stats as d')
            ->join('rooms as r', 'r.id', '=', 'd.room_id')
            ->join('venues as v', 'v.id', '=', 'r.venue_id')
            ->join('groups as g', 'g.id', '=', 'v.group_id')
            ->where('r.is_active', true)
            ->where('r.under_maintenance', false)
            ->where('d.date', '>=', $since)
            ->groupBy('month', 'g.is_ours')
            ->selectRaw("substr(d.date, 1, 7) as month, g.is_ours,
                SUM(d.sold_out) as sold, SUM(d.slots_total) as total,
                SUM(d.am_sold_out) as am_sold, SUM(d.am_total) as am_total,
                SUM(d.pm_sold_out) as pm_sold, SUM(d.pm_total) as pm_total")
            ->orderByDesc('month')
            ->get();

        $months = $rows->pluck('month')->unique()->sortDesc()->values();

        return $months->map(function ($month) use ($rows) {
            $ours = $rows->first(fn ($r) => $r->month === $month && (int) $r->is_ours === 1);
            $comp = $rows->first(fn ($r) => $r->month === $month && (int) $r->is_ours === 0);

            return [
                'month' => Carbon::createFromFormat('Y-m', $month)->format('M Y'),
                'ours' => $this->cell($ours),
                'competitors' => $this->cell($comp),
            ];
        })->all();
    }

    private function cell(?object $r): array
    {
        $pct = fn ($sold, $total) => $total > 0 ? (int) round($sold * 100 / $total) : null;

        return [
            'occupancy' => $r ? $pct($r->sold, $r->total) : null,
            'am' => $r ? $pct($r->am_sold, $r->am_total) : null,
            'pm' => $r ? $pct($r->pm_sold, $r->pm_total) : null,
        ];
    }
}
