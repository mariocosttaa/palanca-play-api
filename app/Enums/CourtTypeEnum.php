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

    public function label(): string
    {
        return __('court_types.' . $this->value);
    }

    public static function options(): array
    {
        return array_map(fn($case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ], self::cases());
    }
}

