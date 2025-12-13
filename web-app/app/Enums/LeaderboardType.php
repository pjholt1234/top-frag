<?php

namespace App\Enums;

enum LeaderboardType: string
{
    case AIM = 'aim';
    case IMPACT = 'impact';
    case ROUND_SWING = 'round_swing';
    case FRAGGER = 'fragger';
    case SUPPORT = 'support';
    case OPENER = 'opener';
    case CLOSER = 'closer';
}
