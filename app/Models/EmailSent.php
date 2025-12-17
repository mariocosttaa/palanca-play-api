<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Enums\EmailTypeEnum;

class EmailSent extends Model
{
    use HasFactory;

    protected $table = 'emails_sent';

    protected $fillable = [
        'user_email',
        'code',
        'type',
        'subject',
        'title',
        'html_content',
        'status',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'type' => EmailTypeEnum::class,
    ];

    // Scopes
    public function scopeForEmail($query, $email)
    {
        return $query->where('user_email', $email);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('sent_at', '>=', now()->subDays($days));
    }
}
