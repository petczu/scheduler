<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComparisonSet extends Model
{
    protected $fillable = ['name', 'our_group_id', 'competitor_group_id'];

    public function ourGroup(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'our_group_id');
    }

    public function competitorGroup(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'competitor_group_id');
    }
}
