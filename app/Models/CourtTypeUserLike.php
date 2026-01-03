<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourtTypeUserLike extends Model
{
    protected $table = 'court_type_user_likes';

    protected $fillable = [
        'user_id',
        'court_type_id',
    ];

    /**
     * Get the user that liked the court type.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the court type that was liked.
     */
    public function courtType(): BelongsTo
    {
        return $this->belongsTo(CourtType::class);
    }
}
