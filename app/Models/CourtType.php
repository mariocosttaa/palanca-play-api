<?php

namespace App\Models;

use App\Enums\CourtTypeEnum;
use App\Traits\HasHashid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourtType extends Model
{
    /** @use HasFactory<\Database\Factories\CourtTypeFactory> */
    use HasFactory, SoftDeletes, HasHashid;

    protected $table = 'courts_type';

    protected $fillable = [
        'tenant_id',
        'type',
        'name',
        'description',
        'interval_time_minutes',
        'buffer_time_minutes',
        'price_per_interval',
        'status',
    ];

    protected $casts = [
        'type' => CourtTypeEnum::class,
        'status' => 'boolean',
        'interval_time_minutes' => 'integer',
        'buffer_time_minutes' => 'integer',
        'price_per_interval' => 'integer',
    ];



    // Relationships
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function courts()
    {
        return $this->hasMany(Court::class, 'court_type_id');
    }

    public function courtsAvailabilities()
    {
        return $this->hasMany(CourtAvailability::class);
    }

    // Scopes
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
}

