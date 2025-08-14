<?php

namespace App\Enums;

enum MatchType: string
{
    case HLTV = 'hltv';
    case MATCHMAKING = 'mm';
    case FACEIT = 'faceit';
    case ESPORTAL = 'esportal';
    case OTHER = 'other';
}
