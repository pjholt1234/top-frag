<?php

namespace App\Enums;

enum GrenadeType: string
{
    case HE_GRENADE = 'hegrenade';
    case FLASHBANG = 'flashbang';
    case SMOKE_GRENADE = 'smokegrenade';
    case MOLOTOV = 'molotov';
    case INCENDIARY = 'incendiary';
    case DECOY = 'decoy';
}
