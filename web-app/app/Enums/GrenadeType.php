<?php

namespace App\Enums;

enum GrenadeType: string
{
    case HE_GRENADE = 'HE Grenade';
    case FLASHBANG = 'Flashbang';
    case SMOKE_GRENADE = 'Smoke Grenade';
    case MOLOTOV = 'Molotov';
    case INCENDIARY = 'Incendiary Grenade';
    case DECOY = 'Decoy Grenade';

    public static function options(): array
    {
        return [
            ['type' => 'fire_grenades', 'displayName' => 'Fire Grenades'],
            ['type' => GrenadeType::SMOKE_GRENADE->value, 'displayName' => 'Smoke Grenade'],
            ['type' => GrenadeType::HE_GRENADE->value, 'displayName' => 'HE Grenade'],
            ['type' => GrenadeType::FLASHBANG->value, 'displayName' => 'Flashbang'],
            ['type' => GrenadeType::DECOY->value, 'displayName' => 'Decoy Grenade'],
        ];
    }
}
