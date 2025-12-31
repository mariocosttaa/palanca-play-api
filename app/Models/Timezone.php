<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Timezone extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'label',
        'offset',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function businessUsers()
    {
        return $this->hasMany(BusinessUser::class);
    }
}
