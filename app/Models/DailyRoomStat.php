<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyRoomStat extends Model
{
    protected $fillable = [
        'room_id',
        'date',
        'slots_total',
        'sold_out',
        'occupancy',
        'released',
        'am_total',
        'am_sold_out',
        'pm_total',
        'pm_sold_out',
    ];

    // `date` is kept as a plain 'Y-m-d' string (no datetime cast) so exact
    // date matching is consistent across MySQL (DATE) and SQLite (tests).

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
