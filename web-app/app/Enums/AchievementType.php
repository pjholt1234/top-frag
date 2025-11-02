<?php

namespace App\Enums;

enum AchievementType: string
{
    case FRAGGER = 'fragger';
    case SUPPORT = 'support';
    case OPENER = 'opener';
    case CLOSER = 'closer';
    case TOP_AIMER = 'top_aimer';
    case IMPACT_PLAYER = 'impact_player';
    case DIFFERENCE_MAKER = 'difference_maker';
}
