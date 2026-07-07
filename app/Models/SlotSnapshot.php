<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlotSnapshot extends Model
{
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_SOLD_OUT = 'sold_out';
    public const STATUS_UNKNOWN = 'unknown';

    protected $fillable = [
        'room_id',
        'scan_run_id',
        'slot_at',
        'status',
        'raw_label',
        'scanned_at',
    ];

    protected function casts(): array
    {
        return [
            'slot_at' => 'datetime',
            'scanned_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function scanRun(): BelongsTo
    {
        return $this->belongsTo(ScanRun::class);
    }
}
