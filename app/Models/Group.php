<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Group extends Model
{
    protected $fillable = ['name', 'is_ours', 'color'];

    protected function casts(): array
    {
        return [
            'is_ours' => 'boolean',
        ];
    }

    public function venues(): HasMany
    {
        return $this->hasMany(Venue::class);
    }

    public function rooms(): HasManyThrough
    {
        return $this->hasManyThrough(Room::class, Venue::class);
    }
}
