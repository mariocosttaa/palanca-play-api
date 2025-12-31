<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

use Illuminate\Contracts\Auth\MustVerifyEmail;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes, HasApiTokens;

    protected $fillable = [
        'name',
        'surname',
        'email',
        'locale',
        'google_login',
        'country_id',
        'calling_code',
        'phone',
        'timezone_id',
        'password',
        'is_app_user',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'google_login' => 'boolean',
            'is_app_user' => 'boolean',
            'locale' => \App\Enums\LocaleEnum::class,
        ];
    }

    // Relationships
    public function timezone()
    {
        return $this->belongsTo(Timezone::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function tenants()
    {
        return $this->belongsToMany(Tenant::class, 'user_tenants')
            ->withTimestamps();
    }

    // Scopes
    public function scopeForCountry($query, $countryId)
    {
        return $query->where('country_id', $countryId);
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->whereHas('tenants', function ($q) use ($tenantId) {
            $q->where('user_tenants.tenant_id', $tenantId);
        });
    }

    // Accessors
    public function getPhoneFormattedAttribute()
    {
        if ($this->calling_code && $this->phone) {
            return '+' . $this->calling_code . ' ' . $this->phone;
        }
        return $this->phone;
    }
}
