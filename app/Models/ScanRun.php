<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScanRun extends Model
{
    protected $fillable = [
        'scan_source_id',
        'status',
        'fetcher',
        'credits_cost',
        'slots_found',
        'rooms_found',
        'error',
        'raw_html_path',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function scanSource(): BelongsTo
    {
        return $this->belongsTo(ScanSource::class);
    }

    public function slotSnapshots(): HasMany
    {
        return $this->hasMany(SlotSnapshot::class);
    }
}
