<?php

namespace App\Models;

use App\Traits\HasHashid;
use App\Traits\HasMoney;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    /** @use HasFactory<\Database\Factories\InvoiceFactory> */
    use HasFactory, HasHashid, HasMoney;

    protected $fillable = [
        'tenant_id',
        'period',
        'date_start',
        'date_end',
        'price',
        'max_courts',
        'status',
        'metadata',
    ];

    protected $casts = [
        'date_start' => 'datetime',
        'date_end' => 'datetime',
        'price' => 'integer',
        'max_courts' => 'integer',
        'metadata' => 'array',
    ];

    // Relationships
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    // Scopes
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('date_start', [$startDate, $endDate]);
    }

    public function scopeValid($query)
    {
        return $query->where('status', 'paid')
                     ->where('date_end', '>=', now());
    }
}

