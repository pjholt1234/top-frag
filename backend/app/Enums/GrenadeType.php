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
}
