<?php

namespace App\Enums;

enum MatchEventType: string
{
    case DAMAGE = 'damage';
    case GUNFIGHT = 'gunfight';
    case GRENADE = 'grenade';
    case ROUND = 'round';
}
