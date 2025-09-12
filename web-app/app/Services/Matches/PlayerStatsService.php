<?php

namespace App\Services\Matches;

use App\Models\GameMatch;
use App\Models\PlayerMatchEvent;
use App\Models\User;
use App\Services\MatchCacheManager;

class PlayerStatsService
{
    public function getStats(User $user, int $matchId): array
    {
        // Check user access first
        if (!$this->hasUserAccessToMatch($user, $matchId)) {
            return [];
        }

        return [];
    }
}
