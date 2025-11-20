<?php

namespace App\Enums;

enum CourtTypeEnum: string
{
    case FOOTBALL = 'football';
    case BASKETBALL = 'basketball';
    case TENNIS = 'tennis';
    case SQUASH = 'squash';
    case BADMINTON = 'badminton';
    case PADEL = 'padel';
    case OTHER = 'other';

    /**
     * Get all enum values as an array
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

