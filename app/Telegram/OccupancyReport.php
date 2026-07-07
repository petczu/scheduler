<?php

namespace App\Telegram;

use App\Models\Group;
use App\Models\Room;
use App\Models\TelegramTemplate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Builds Telegram digest texts and detects alert-worthy events from the
 * latest scan data. All occupancy figures ignore maintenance rooms.
 */
class OccupancyReport
{
    /**
     * @return Collection<int, array{venue: string, is_ours: bool, total: int, sold: int, occupancy: int|null}>
     */
    public function venueStats(): Collection
    {
        return Group::query()
            ->with(['venues' => fn ($q) => $q->where('is_active', true), 'venues.rooms' => fn ($q) => $q->counted()])
            ->get()
            ->flatMap(function (Group $group) {
                return $group->venues->map(function ($venue) use ($group) {
                    $total = 0;
                    $sold = 0;

                    foreach ($venue->rooms as $room) {
                        $stats = $room->todayStats();
                        $total += $stats['total'];
                        $sold += $stats['sold_out'];
                    }

                    return [
                        'venue' => $venue->name,
                        'is_ours' => $group->is_ours,
                        'total' => $total,
                        'sold' => $sold,
                        'occupancy' => $total > 0 ? (int) round($sold / $total * 100) : null,
                    ];
                });
            })
            ->filter(fn ($row) => $row['total'] > 0)
            ->values();
    }

    public function digest(string $period): string
    {
        $date = CarbonImmutable::now('Asia/Dubai')->format('D, d M Y');
        $headerKey = $period === 'evening' ? 'digest_evening_header' : 'digest_morning_header';

        $stats = $this->venueStats();
        $ours = $stats->where('is_ours', true)->sortByDesc('occupancy');
        $competitors = $stats->where('is_ours', false)->sortByDesc('occupancy');

        $lines = [TelegramTemplate::render($headerKey, ['date' => $date]), ''];

        $lines[] = TelegramTemplate::render('digest_group_ours');
        $lines = array_merge($lines, $this->rows($ours));
        $lines[] = '';
        $lines[] = TelegramTemplate::render('digest_group_competitors');
        $lines = array_merge($lines, $this->rows($competitors));

        $leader = $stats->sortByDesc('occupancy')->first();
        if ($leader && $leader['occupancy'] !== null) {
            $lines[] = '';
            $lines[] = TelegramTemplate::render('digest_leader', [
                'venue' => $leader['venue'],
                'occupancy' => $leader['occupancy'],
            ]);
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<int, array{kind: string, signature: string, text: string}>
     */
    public function detectAlerts(): array
    {
        $today = CarbonImmutable::now('Asia/Dubai')->format('Y-m-d');
        $threshold = (int) config('services.telegram.sold_out_threshold', 80);
        $alerts = [];

        $rooms = Room::query()
            ->counted()
            ->with('venue.group')
            ->whereHas('venue', fn ($q) => $q->where('is_active', true))
            ->get();

        foreach ($rooms as $room) {
            $isOurs = $room->venue->group->is_ours;
            $stats = $room->todayStats();
            $where = "{$room->venue->name} / {$room->name}";

            // Fake booking: a competitor's future slot freed up after being sold out.
            if (! $isOurs && $stats['released'] > 0) {
                $alerts[] = [
                    'kind' => 'alert_fake_booking',
                    'signature' => "alert_fake_booking:room={$room->id}:{$today}",
                    'text' => TelegramTemplate::render('alert_fake_booking', [
                        'where' => $where,
                        'count' => $stats['released'],
                    ]),
                ];
            }

            // Competitor selling out today.
            if (! $isOurs && $stats['occupancy'] !== null && $stats['occupancy'] >= $threshold) {
                $alerts[] = [
                    'kind' => 'alert_sold_out',
                    'signature' => "alert_sold_out:room={$room->id}:{$today}",
                    'text' => TelegramTemplate::render('alert_sold_out', [
                        'where' => $where,
                        'occupancy' => $stats['occupancy'],
                        'sold' => $stats['sold_out'],
                        'total' => $stats['total'],
                    ]),
                ];
            }
        }

        return $alerts;
    }

    /**
     * @param  Collection<int, array{venue: string, occupancy: int|null, sold: int, total: int}>  $rows
     * @return string[]
     */
    protected function rows(Collection $rows): array
    {
        if ($rows->isEmpty()) {
            return [TelegramTemplate::render('digest_empty')];
        }

        return $rows->map(function ($row) {
            $pct = $row['occupancy'] === null ? '—' : "{$row['occupancy']}%";

            return TelegramTemplate::render('digest_row', [
                'venue' => $row['venue'],
                'occupancy' => $pct,
                'sold' => $row['sold'],
                'total' => $row['total'],
            ]);
        })->all();
    }
}
