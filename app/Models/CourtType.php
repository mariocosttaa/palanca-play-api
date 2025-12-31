<?php

namespace App\Models;

use App\Actions\General\MoneyAction;
use App\Enums\CourtTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourtType extends Model
{
    /** @use HasFactory<\Database\Factories\CourtTypeFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'courts_type';

    protected $fillable = [
        'tenant_id',
        'type',
        'name',
        'description',
        'interval_time_minutes',
        'buffer_time_minutes',
        'price_per_interval',
        'likes_count',
        'status',
    ];

    protected $casts = [
        'type' => CourtTypeEnum::class,
        'status' => 'boolean',
        'interval_time_minutes' => 'integer',
        'buffer_time_minutes' => 'integer',
        'price_per_interval' => 'integer',
        'likes_count' => 'integer',
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

    public function availabilities()
    {
        return $this->hasMany(CourtAvailability::class);
    }

    public function likes()
    {
        return $this->belongsToMany(User::class, 'court_type_user_likes')
            ->withTimestamps();
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

    // Accessors
    public function getPriceFormattedAttribute()
    {
        return MoneyAction::format(
            amount: $this->price_per_interval,
            currency: $this->tenant->currency,
            formatWithSymbol: true
        );
    }

    public function getIsLikedAttribute()
    {
        if (!auth()->check()) {
            return false;
        }

        return $this->likes()->where('user_id', auth()->id())->exists();
    }
}
