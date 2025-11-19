<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourtImage extends Model
{
    /** @use HasFactory<\Database\Factories\CourtImageFactory> */
    use HasFactory;

    protected $table = 'courts_images';

    protected $fillable = [
        'court_id',
        'path',
        'alt',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    // Relationships
    public function court()
    {
        return $this->belongsTo(Court::class);
    }

    // Scopes
    public function scopeForCourt($query, $courtId)
    {
        return $query->where('court_id', $courtId);
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }
}

