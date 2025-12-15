<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PasswordResetCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'code',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    // Scopes
    public function scopeValid($query)
    {
        return $query->where('expires_at', '>', now())
            ->whereNull('used_at');
    }

    public function scopeForEmail($query, $email)
    {
        return $query->where('email', $email);
    }

    // Helper methods
    public function isExpired()
    {
        return $this->expires_at < now();
    }

    public function isUsed()
    {
        return !is_null($this->used_at);
    }

    public function isValid()
    {
        return !$this->isExpired() && !$this->isUsed();
    }

    public function markAsUsed()
    {
        $this->update(['used_at' => now()]);
    }

    /**
     * Generate a random 6-digit code
     */
    public static function generateCode()
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
