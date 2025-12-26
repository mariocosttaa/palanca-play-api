<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\BookingStatusEnum;
use App\Enums\PaymentStatusEnum;
use App\Enums\PaymentMethodEnum;
use App\Models\Manager\CurrencyModel;

class Booking extends Model
{
    /** @use HasFactory<\Database\Factories\BookingFactory> */
    use HasFactory;

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
        'status',
        'payment_status',
        'payment_method',
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
        'status' => BookingStatusEnum::class,
        'payment_status' => PaymentStatusEnum::class,
        'payment_method' => PaymentMethodEnum::class,
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
        return $this->belongsTo(CurrencyModel::class);
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
        return $query->where('status', BookingStatusEnum::PENDING);
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', BookingStatusEnum::CONFIRMED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', BookingStatusEnum::CANCELLED);
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', PaymentStatusEnum::PAID);
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

