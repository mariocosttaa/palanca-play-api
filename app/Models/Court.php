<?php

namespace App\Models;

use App\Traits\HasHashid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Court extends Model
{
    /** @use HasFactory<\Database\Factories\CourtFactory> */
    use HasFactory, SoftDeletes, HasHashid;

    protected $table = 'courts';

    protected $fillable = [
        'tenant_id',
        'court_type_id',
        'name',
        'number',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    // Relationships
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function courtType()
    {
        return $this->belongsTo(CourtType::class, 'court_type_id');
    }

    public function images()
    {
        return $this->hasMany(CourtImage::class);
    }

    public function primaryImage()
    {
        return $this->hasOne(CourtImage::class)->where('is_primary', true);
    }

    public function courtsAvailabilities()
    {
        return $this->hasMany(CourtAvailability::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    // Scopes
    public function scopeForCourtType($query, $courtTypeId)
    {
        return $query->where('court_type_id', $courtTypeId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    // Accessors
    public function getEffectiveAvailabilityAttribute()
    {
        if ($this->courtsAvailabilities()->exists()) {
            return $this->courtsAvailabilities;
        }

        return $this->courtType->courtsAvailabilities ?? collect();
    }
}

