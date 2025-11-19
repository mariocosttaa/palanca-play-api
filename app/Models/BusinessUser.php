<?php

namespace App\Models;

use App\Traits\HasHashid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class BusinessUser extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes, HasHashid, HasApiTokens;

    protected $fillable = [
        'name',
        'surname',
        'email',
        'google_login',
        'country_id',
        'calling_code',
        'phone',
        'timezone',
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

