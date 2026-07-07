<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScanSource extends Model
{
    protected $fillable = [
        'venue_id',
        'name',
        'url',
        'strategy',
        'fetcher',
        'render_js',
        'anti_bot',
        'parse_mode',
        'available_only',
        'parser_config',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'render_js' => 'boolean',
            'anti_bot' => 'boolean',
            'available_only' => 'boolean',
            'is_active' => 'boolean',
            'parser_config' => 'array',
        ];
    }

    /**
     * URL with placeholders resolved: {today} becomes the current date
     * (Y-m-d) in the venue's timezone.
     */
    public function resolvedUrl(): string
    {
        return str_replace(
            '{today}',
            now($this->venue->timezone)->format('Y-m-d'),
            $this->url,
        );
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function scanRuns(): HasMany
    {
        return $this->hasMany(ScanRun::class);
    }
}
