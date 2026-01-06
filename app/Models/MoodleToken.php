<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class MoodleToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'token',
        'token_type',
        'expires_in',
        'expires_at',
        'scope',
        'refresh_token',
        'moodle_user_id',
        'is_active'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_active' => 'boolean'
    ];

    protected $hidden = [
        'token',
        'refresh_token'
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Methods
    public function isValid()
    {
        return $this->is_active && 
               $this->expires_at && 
               $this->expires_at->gt(now());
    }

    public function isExpired()
    {
        return $this->expires_at && $this->expires_at->lt(now());
    }

    public function expire()
    {
        $this->update([
            'is_active' => false,
            'expires_at' => now()
        ]);
    }

    // Changed from refresh() to refreshToken() to avoid conflict with Model::refresh()
    public function refreshToken(array $newTokenData)
    {
        $this->update([
            'token' => $newTokenData['access_token'],
            'token_type' => $newTokenData['token_type'] ?? 'Bearer',
            'expires_in' => $newTokenData['expires_in'],
            'expires_at' => now()->addSeconds($newTokenData['expires_in'] - 60),
            'refresh_token' => $newTokenData['refresh_token'] ?? $this->refresh_token,
            'is_active' => true
        ]);
    }

    // Scopes
    public function scopeValid($query)
    {
        return $query->where('is_active', true)
            ->where('expires_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}