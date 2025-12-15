<?php

namespace App\Models;

use App\Traits\HasHashid;
use App\Traits\HasMoney;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    /** @use HasFactory<\Database\Factories\BookingFactory> */
    use HasFactory, HasHashid, HasMoney;

    protected $fillable = [
        'tenant_id',
        'court_id',
        'user_id',
        'currency_id',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'price',
        'is_pending',   
        'is_cancelled',
        'is_paid',
        'paid_at_venue',
        'present',
        'qr_code',
        'qr_code_verified',
    ];

    protected $casts = [
        'currency_id' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'price' => 'integer', // Stored in cents
        'is_pending' => 'boolean',
        'is_cancelled' => 'boolean',
        'is_paid' => 'boolean',
        'paid_at_venue' => 'boolean',
        'present' => 'boolean',
        'qr_code_verified' => 'boolean',
    ];

    // Relationships
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function court()
    {
        return $this->belongsTo(Court::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function currency()
    {
        return $this->belongsTo(\App\Models\Manager\CurrencyModel::class);
    }

    // Scopes
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForCourt($query, $courtId)
    {
        return $query->where('court_id', $courtId);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopePending($query)
    {
        return $query->where('is_pending', true);
    }

    public function scopeConfirmed($query)
    {
        return $query->where('is_pending', false)->where('is_cancelled', false);
    }

    public function scopeCancelled($query)
    {
        return $query->where('is_cancelled', true);
    }

    public function scopePaid($query)
    {
        return $query->where('is_paid', true);
    }

    public function scopeOnDate($query, $date)
    {
        return $query->where('start_date', $date);
    }

    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('start_date', [$startDate, $endDate]);
    }
}

