<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Timezone extends Model
{
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
