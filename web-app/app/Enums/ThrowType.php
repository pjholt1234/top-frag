<?php

namespace App\Enums;

enum ThrowType: string
{
    case LINEUP = 'lineup';
    case REACTION = 'reaction';
    case PRE_AIM = 'pre_aim';
    case UTILITY = 'utility';
}
