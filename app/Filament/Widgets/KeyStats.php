<?php

namespace App\Filament\Widgets;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Top-of-dashboard key metrics: our vs competitor occupancy today (with a
 * 7-day sparkline), today's leader, and fake bookings spotted. All figures
 * come from the compact daily_room_stats rollup, excluding inactive and
 * maintenance rooms.
 */
class KeyStats extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        $today = Carbon::now('Asia/Dubai')->toDateString();
        $since = Carbon::now('Asia/Dubai')->subDays(6)->toDateString();

        $rows = DB::table('daily_room_stats as d')
            ->join('rooms as r', 'r.id', '=', 'd.room_id')
            ->join('venues as v', 'v.id', '=', 'r.venue_id')
            ->join('groups as g', 'g.id', '=', 'v.group_id')
            ->where('r.is_active', true)
            ->where('r.under_maintenance', false)
            ->where('d.date', '>=', $since)
            ->groupBy('d.date', 'g.is_ours')
            ->selectRaw('d.date, g.is_ours, SUM(d.sold_out) as sold, SUM(d.slots_total) as total, SUM(d.released) as released')
            ->get();

        $days = collect(range(6, 0))->map(fn ($i) => Carbon::now('Asia/Dubai')->subDays($i)->toDateString());

        $ourSpark = $this->sparkline($rows, $days, 1);
        $compSpark = $this->sparkline($rows, $days, 0);

        $ourToday = end($ourSpark) ?: 0;
        $compToday = end($compSpark) ?: 0;
        $released = (int) $rows->where('is_ours', 0)->firstWhere('date', $today)?->released;
        [$leaderName, $leaderOcc] = $this->leaderToday($today);

        $lead = $ourToday - $compToday;

        return [
            Stat::make('Our occupancy today', "{$ourToday}%")
                ->description($lead >= 0 ? "+{$lead} pts vs competitors" : abs($lead).' pts behind')
                ->descriptionIcon($lead >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->chart($ourSpark)
                ->color('success'),

            Stat::make('Competitors today', "{$compToday}%")
                ->description('Average across all competitor rooms')
                ->chart($compSpark)
                ->color('gray'),

            Stat::make('Leader today', $leaderName ?: '—')
                ->description($leaderOcc !== null ? "{$leaderOcc}% booked" : 'No data yet')
                ->descriptionIcon('heroicon-m-trophy')
                ->color('warning'),

            Stat::make('Fake bookings today', (string) $released)
                ->description('Competitor slots freed up after being sold out')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($released > 0 ? 'danger' : 'gray'),
        ];
    }

    /**
     * @return int[] weighted occupancy % per day for the group
     */
    protected function sparkline(Collection $rows, Collection $days, int $isOurs): array
    {
        return $days->map(function ($date) use ($rows, $isOurs) {
            $row = $rows->first(fn ($r) => $r->date === $date && (int) $r->is_ours === $isOurs);

            return ($row && $row->total > 0) ? (int) round($row->sold * 100 / $row->total) : 0;
        })->all();
    }

    /**
     * @return array{0: ?string, 1: ?int} [venue name, occupancy %]
     */
    protected function leaderToday(string $today): array
    {
        $venue = DB::table('daily_room_stats as d')
            ->join('rooms as r', 'r.id', '=', 'd.room_id')
            ->join('venues as v', 'v.id', '=', 'r.venue_id')
            ->where('r.is_active', true)
            ->where('r.under_maintenance', false)
            ->where('d.date', $today)
            ->groupBy('v.id', 'v.name')
            ->havingRaw('SUM(d.slots_total) > 0')
            ->selectRaw('v.name, ROUND(SUM(d.sold_out) * 100.0 / SUM(d.slots_total)) as occ')
            ->orderByDesc('occ')
            ->first();

        return $venue ? [$venue->name, (int) $venue->occ] : [null, null];
    }
}
