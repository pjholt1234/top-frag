<?php

namespace App\Enums;

enum MapType: string
{
    case ANCIENT = 'de_ancient';
    case DUST2 = 'de_dust2';
    case MIRAGE = 'de_mirage';
    case INFERNO = 'de_inferno';
    case NUKE = 'de_nuke';
    case OVERPASS = 'de_overpass';
    case TRAIN = 'de_train';
    case CACHE = 'de_cache';
    case ANUBIS = 'de_anubis';
    case VERTIGO = 'de_vertigo';

    /**
     * Human-readable display name
     */
    public function label(): string
    {
        return match ($this) {
            self::ANCIENT => 'Ancient',
            self::DUST2 => 'Dust II',
            self::MIRAGE => 'Mirage',
            self::INFERNO => 'Inferno',
            self::NUKE => 'Nuke',
            self::OVERPASS => 'Overpass',
            self::TRAIN => 'Train',
            self::CACHE => 'Cache',
            self::ANUBIS => 'Anubis',
            self::VERTIGO => 'Vertigo',
        };
    }

    /**
     * Returns an array of all maps for dropdowns, API responses, etc.
     */
    public static function options(): array
    {
        return array_map(fn ($map) => [
            'name' => $map->value,
            'displayName' => $map->label(),
        ], self::cases());
    }
}
