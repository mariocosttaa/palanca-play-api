<?php

namespace App\Enums;

enum UserPermissionEnum: int
{
    case NORMAL = 1;
    case MODERATOR = 2;
    case ADMIN = 3;

    /**
     * Get all enum values as an array
     *
     * @return array<int>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::NORMAL => 'Normal',
            self::MODERATOR => 'Moderador',
            self::ADMIN => 'Administrador',
        };
    }
}
