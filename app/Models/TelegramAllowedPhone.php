<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramAllowedPhone extends Model
{
    protected $fillable = ['label', 'phone'];

    protected function casts(): array
    {
        return [
            // Encrypted at rest; transparently decrypted when read.
            'phone' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        // Keep the (non-reversible) match hash in sync with the phone.
        static::saving(function (TelegramAllowedPhone $model) {
            $model->phone_hash = static::hash($model->phone);
        });
    }

    public static function normalize(?string $phone): string
    {
        return preg_replace('/\D/', '', (string) $phone);
    }

    /**
     * Deterministic keyed hash of the phone's digits, so we can match an
     * incoming number without storing a queryable plaintext copy.
     */
    public static function hash(?string $phone): string
    {
        return hash_hmac('sha256', static::normalize($phone), (string) config('app.key'));
    }

    public static function allows(?string $phone): bool
    {
        return static::normalize($phone) !== ''
            && static::where('phone_hash', static::hash($phone))->exists();
    }
}
