<?php

namespace App\Enums;

enum LocaleEnum: string
{
    case EN = 'en';
    case PT = 'pt';
    case ES = 'es';
    case FR = 'fr';

    public function label(): string
    {
        return match ($this) {
            self::EN => 'English',
            self::PT => 'Português',
            self::ES => 'Español',
            self::FR => 'Français',
        };
    }
}
