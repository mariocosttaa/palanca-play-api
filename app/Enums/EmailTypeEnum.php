<?php

namespace App\Enums;

enum EmailTypeEnum: string
{
    case GENERAL = 'general';
    case CONFIRMATION_EMAIL = 'confirmation_email';
    case PASSWORD_CHANGE = 'password_change';
    case BOOKING = 'booking';
    case BOOKING_CANCELLED = 'booking_cancelled';
    case BOOKING_EDITED = 'booking_edited';
}
