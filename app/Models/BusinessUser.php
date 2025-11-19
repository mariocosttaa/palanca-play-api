<?php

namespace App\Models;

use App\Traits\HasHashid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusinessUser extends Model
{
    use HasFactory, SoftDeletes, HasHashid;

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

    protected $casts = [
        'google_login' => 'boolean',
    ];

    // Relationships
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function tenants()
    {
        return $this->belongsToMany(Tenant::class, 'business_users_tenants')
            ->withPivot('role')
            ->withTimestamps();
    }

    // Scopes
    public function scopeForCountry($query, $countryId)
    {
        return $query->where('country_id', $countryId);
    }
}

