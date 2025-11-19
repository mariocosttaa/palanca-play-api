<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessUserTenant extends Model
{
    /** @use HasFactory<\Database\Factories\BusinessUserTenantFactory> */
    use HasFactory;

    protected $fillable = [
        'business_user_id',
        'tenant_id',
    ];

    protected $casts = [
        // No special casts needed
    ];

    // Relationships
    public function businessUser()
    {
        return $this->belongsTo(BusinessUser::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}

