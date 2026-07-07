<?php

namespace App\Console\Commands;

use App\Models\DailyRoomStat;
use App\Models\Room;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Rolls raw slot snapshots into one compact daily_room_stats row per room per
 * day, so year-scale analysis is fast and cheap. Idempotent (updateOrCreate),
 * so it can be re-run to backfill or correct a day.
 *
 *   php artisan stats:rollup                 # yesterday
 *   php artisan stats:rollup --date=2026-07-01
 *   php artisan stats:rollup --days=30       # last 30 days (ending yesterday)
 *   php artisan stats:rollup --today         # include today (partial)
 */
class RollupStats extends Command
{
    protected $signature = 'stats:rollup
        {--date= : Roll up a specific day (YYYY-MM-DD, venue-local)}
        {--days= : Roll up the last N days ending yesterday}
        {--today : Also roll up today (partial)}';

    protected $description = 'Aggregate slot snapshots into compact daily room stats';

    public function handle(): int
    {
        $dates = $this->targetDates();
        $rooms = Room::with('venue')->get();
        $written = 0;

        foreach ($rooms as $room) {
            $tz = $room->venue->timezone;

            foreach ($dates as $dateString) {
                $day = CarbonImmutable::parse($dateString, $tz)->startOfDay();
                $stats = $room->dayStats($day);

                // Skip days with no data at all (keeps the table sparse).
                if ($stats['total'] === 0) {
                    continue;
                }

                DailyRoomStat::updateOrCreate(
                    ['room_id' => $room->id, 'date' => $day->toDateString()],
                    [
                        'slots_total' => $stats['total'],
                        'sold_out' => $stats['sold_out'],
                        'occupancy' => $stats['occupancy'],
                        'released' => $stats['released'],
                        'am_total' => $stats['am_total'],
                        'am_sold_out' => $stats['am_sold_out'],
                        'pm_total' => $stats['pm_total'],
                        'pm_sold_out' => $stats['pm_sold_out'],
                    ],
                );

                $written++;
            }
        }

        $this->info("Rolled up {$written} room-day stat(s) across ".count($dates).' day(s).');

        return self::SUCCESS;
    }

    /**
     * @return string[] Y-m-d dates to roll up
     */
    protected function targetDates(): array
    {
        // Reference "today" in the primary timezone (venues are Asia/Dubai).
        $today = CarbonImmutable::now('Asia/Dubai')->startOfDay();

        if ($this->option('date')) {
            return [CarbonImmutable::parse($this->option('date'))->toDateString()];
        }

        if ($this->option('days')) {
            $n = max(1, (int) $this->option('days'));
            $dates = [];
            for ($i = 1; $i <= $n; $i++) {
                $dates[] = $today->subDays($i)->toDateString();
            }

            return $dates;
        }

        $dates = [$today->subDay()->toDateString()];
        if ($this->option('today')) {
            $dates[] = $today->toDateString();
        }

        return $dates;
    }
}
