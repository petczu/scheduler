<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramSubscriber extends Model
{
    protected $fillable = [
        'chat_id',
        'phone',
        'first_name',
        'username',
        'authorized',
        'authorized_at',
    ];

    protected function casts(): array
    {
        return [
            'phone' => 'encrypted',
            'authorized' => 'boolean',
            'authorized_at' => 'datetime',
        ];
    }

    public function scopeAuthorized($query)
    {
        return $query->where('authorized', true);
    }
}
