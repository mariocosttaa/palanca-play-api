<?php

namespace App\Models;

use App\Traits\HasHashid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    use HasFactory, HasHashid;

    protected $fillable = [
        'name',
        'slug',
        'max_courts',
        'price',
    ];

    protected $casts = [
        'max_courts' => 'integer',
        'price' => 'decimal:2',
    ];

    // Relationships
    public function tenants()
    {
        return $this->hasMany(Tenant::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}

