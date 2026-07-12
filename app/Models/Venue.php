<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venue extends Model
{
    protected $fillable = ['group_id', 'name', 'website_url', 'timezone', 'is_active', 'booking_cutoff_minutes'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'booking_cutoff_minutes' => 'integer',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function scanSources(): HasMany
    {
        return $this->hasMany(ScanSource::class);
    }
}
