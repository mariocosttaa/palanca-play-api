<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'capital_city',
        'code',
        'calling_code',
    ];

    protected $casts = [
        // No special casts needed
    ];

    // Relationships
    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function businessUsers()
    {
        return $this->hasMany(BusinessUser::class);
    }
}

