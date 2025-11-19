<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourtAvailability extends Model
{
    use HasFactory;

    protected $table = 'courts_availabilities';

    protected $fillable = [
        'tenant_id',
        'court_id',
        'court_type_id',
        'day_of_week_recurring',
        'specific_date',
        'start_time',
        'end_time',
        'breaks',
        'is_available',
    ];

    protected $casts = [
        'specific_date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'breaks' => 'array',
        'is_available' => 'boolean',
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

    public function courtType()
    {
        return $this->belongsTo(CourtType::class);
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

    public function scopeForCourtType($query, $courtTypeId)
    {
        return $query->where('court_type_id', $courtTypeId);
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    public function scopeRecurring($query)
    {
        return $query->whereNotNull('day_of_week_recurring');
    }

    public function scopeSpecificDate($query, $date)
    {
        return $query->where('specific_date', $date);
    }
}

