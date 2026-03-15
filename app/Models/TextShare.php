<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TextShare extends Model
{
    protected $fillable = [
        'hash_id',
        'user_id',
        'browser_id',
        'content',
        'format',
        'password',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    protected $hidden = [
        'password',
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

    /**
     * Check if text share is password protected
     */
    public function isPasswordProtected(): bool
    {
        return !empty($this->password);
    }

    /**
     * Verify password
     */
    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->password);
    }

    /**
     * Scope to get shares by browser ID
     */
    public function scopeByBrowser(Builder $query, string $browserId): Builder
    {
        return $query->where('browser_id', $browserId);
    }

    /**
     * Get the user that owns this text share
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
