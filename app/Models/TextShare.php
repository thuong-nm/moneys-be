<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TextShare extends Model
{
    protected $fillable = [
        'hash_id',
        'content',
        'format',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Generate a unique hash ID for the text share
     */
    public static function generateHashId(): string
    {
        do {
            // Generate 10 random alphanumeric characters
            $hashId = Str::random(10);
        } while (self::where('hash_id', $hashId)->exists());

        return $hashId;
    }

    /**
     * Scope to get only non-expired text shares
     */
    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }
}
