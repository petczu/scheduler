<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    protected ?array $todayStats = null;

    protected $fillable = [
        'venue_id',
        'scan_source_id',
        'name',
        'match_label',
        'is_active',
        'under_maintenance',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'under_maintenance' => 'boolean',
        ];
    }

    /**
     * Rooms that count toward occupancy metrics (active, not on maintenance).
     */
    public function scopeCounted($query)
    {
        return $query->where('is_active', true)->where('under_maintenance', false);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function scanSource(): BelongsTo
    {
        return $this->belongsTo(ScanSource::class);
    }

    public function slotSnapshots(): HasMany
    {
        return $this->hasMany(SlotSnapshot::class);
    }

    /**
     * The card title on the source page this room is matched against.
     */
    public function matchLabel(): string
    {
        return $this->match_label ?? $this->name;
    }

    /**
     * Today's occupancy for this room (memoized). See dayStats() for shape.
     */
    public function todayStats(): array
    {
        return $this->todayStats ??= $this->dayStats(
            CarbonImmutable::now($this->venue->timezone)->startOfDay()
        );
    }

    /**
     * Occupancy for a given venue-local day, based on that day's scans.
     *
     * IMPORTANT: a slot only counts as booked if it was sold out WHILE STILL
     * BOOKABLE — i.e. at the last scan taken before its start time. On many
     * sites a past slot is shown as "disabled"/sold-out simply because its
     * time has passed; those are ignored (they are not real bookings). Slots
     * we never observed while they were still upcoming are excluded entirely.
     *
     * - occupancy = booked / observed slots (latest pre-start reading);
     * - released counts slots that went sold-out then free again while still
     *   bookable (a likely fake booking or cancellation);
     * - am_* / pm_* split slots by start hour (< 17 morning, >= 17 evening).
     *
     * @return array{total:int, sold_out:int, released:int, occupancy:?int,
     *               am_total:int, am_sold_out:int, pm_total:int, pm_sold_out:int}
     */
    public function dayStats(CarbonImmutable $day): array
    {
        $tz = $this->venue->timezone;
        $day = $day->startOfDay();

        $bySlot = $this->slotSnapshots()
            ->whereBetween('slot_at', [$day, $day->endOfDay()])
            ->orderBy('scanned_at')
            ->get()
            ->groupBy(fn (SlotSnapshot $s) => $s->slot_at->format('H:i'));

        $total = 0;
        $soldOut = 0;
        $released = 0;
        $amTotal = $amSold = $pmTotal = $pmSold = 0;

        foreach ($bySlot as $time => $history) {
            // Real start instant of this slot (slot_at is stored venue-local).
            $slotStart = CarbonImmutable::createFromFormat(
                'Y-m-d H:i:s',
                $history->first()->slot_at->format('Y-m-d H:i:s'),
                $tz,
            );

            // Only readings taken before the slot started tell us if it was booked.
            $preStart = $history->filter(fn (SlotSnapshot $s) => $s->scanned_at->lessThan($slotStart));

            if ($preStart->isEmpty()) {
                continue; // never observed while still bookable → unknown, skip
            }

            $total++;
            $isSold = $preStart->last()->status === SlotSnapshot::STATUS_SOLD_OUT;
            $isMorning = (int) substr($time, 0, 2) < 17;

            if ($isMorning) {
                $amTotal++;
                $amSold += $isSold ? 1 : 0;
            } else {
                $pmTotal++;
                $pmSold += $isSold ? 1 : 0;
            }

            if ($isSold) {
                $soldOut++;
            }

            $wasSold = false;
            foreach ($preStart as $snapshot) {
                if ($snapshot->status === SlotSnapshot::STATUS_SOLD_OUT) {
                    $wasSold = true;
                } elseif ($wasSold && $snapshot->status === SlotSnapshot::STATUS_AVAILABLE) {
                    $released++;
                    break;
                }
            }
        }

        return [
            'total' => $total,
            'sold_out' => $soldOut,
            'released' => $released,
            'occupancy' => $total > 0 ? (int) round($soldOut / $total * 100) : null,
            'am_total' => $amTotal,
            'am_sold_out' => $amSold,
            'pm_total' => $pmTotal,
            'pm_sold_out' => $pmSold,
        ];
    }
}
