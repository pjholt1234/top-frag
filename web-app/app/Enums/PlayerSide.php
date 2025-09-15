<?php

namespace App\Enums;

enum PlayerSide: string
{
    case CT = 'CT';
    case T = 'T';

    /**
     * Human-readable display name
     */
    public function label(): string
    {
        return match ($this) {
            self::CT => 'Counter-Terrorist',
            self::T => 'Terrorist',
        };
    }

    /**
     * Returns an array suitable for dropdowns or API responses
     */
    public static function options(): array
    {
        return array_map(fn ($side) => [
            'side' => $side->value,
            'displayName' => $side->label(),
        ], self::cases());
    }
}
