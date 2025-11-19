<?php

namespace App\Models;

use App\Traits\HasHashid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    /** @use HasFactory<\Database\Factories\TenantFactory> */
    use HasFactory, SoftDeletes, HasHashid;

    protected $fillable = [
        'name',
        'address',
        'latitude',
        'longitude',
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
    public function subscriptionPlan()
    {
        return $this->hasOne(SubscriptionPlan::class);
    }

    public function businessUsers()
    {
        return $this->belongsToMany(BusinessUser::class, 'business_users_tenants')
            ->withTimestamps();
    }

    public function courtTypes()
    {
        return $this->hasMany(CourtType::class);
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
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('id', $tenantId);
    }

    public function scopeWithAutoConfirm($query)
    {
        return $query->where('auto_confirm_bookings', true);
    }
}

