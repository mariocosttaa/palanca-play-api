<?php

namespace App\Models;

use App\Traits\HasHashid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    use HasFactory, HasHashid;

    protected $table = 'subscription_plan';

    protected $fillable = [
        'tenant_id',
        'courts',
        'price',
    ];

    protected $casts = [
        'courts' => 'integer',
        'price' => 'integer',
    ];

    // Relationships
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

}

