<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    /** @use HasFactory<\Database\Factories\TenantFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'country_id',
        'name',
        'logo',
        'address',
        'latitude',
        'longitude',
        'currency',
        'timezone',
        'timezone_id',
        'auto_confirm_bookings',
        'booking_interval_minutes',
        'buffer_between_bookings_minutes',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'auto_confirm_bookings' => 'boolean',
        'booking_interval_minutes' => 'integer',
        'buffer_between_bookings_minutes' => 'integer',
    ];

    // Relationships
    public function timezone()
    {
        return $this->belongsTo(Timezone::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function subscriptionPlan()
    {
        return $this->hasOne(SubscriptionPlan::class);
    }

    public function businessUsers()
    {
        return $this->belongsToMany(BusinessUser::class, 'business_users_tenants')
            ->withTimestamps();
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_tenants')
            ->withTimestamps();
    }

    public function courtTypes()
    {
        return $this->hasMany(CourtType::class);
    }

    public function courts()
    {
        return $this->hasMany(Court::class);
    }

    public function courtsAvailabilities()
    {
        return $this->hasMany(CourtAvailability::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    // Scopes
    public function scopeForBusinessUser($query, $businessUserId)
    {
        return $query->whereHas('businessUsers', function ($query) use ($businessUserId) {
            $query->where('business_users_tenants.business_user_id', $businessUserId);
        });
    }
}

