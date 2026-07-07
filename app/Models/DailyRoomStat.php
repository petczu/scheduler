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

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
