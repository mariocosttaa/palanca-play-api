<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class BusinessUser extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\BusinessUserFactory> */
    use HasFactory, Notifiable, SoftDeletes, HasApiTokens;

    protected $fillable = [
        'name',
        'surname',
        'email',
        'google_login',
        'country_id',
        'calling_code',
        'phone',
        'timezone',
        'language',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'google_login' => 'boolean',
            'language' => 'string',
            'email_verified_at' => 'datetime',
        ];
    }

    // Relationships
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function tenants()
    {
        return $this->belongsToMany(Tenant::class, 'business_users_tenants')
            ->withTimestamps();
    }

    // Scopes
    public function scopeForCountry($query, $countryId)
    {
        return $query->where('country_id', $countryId);
    }
}

