<?php

namespace App\Services\Matches;

use App\Models\User;

class PlayerStatsService
{
    use MatchAccessTrait;

    public function getStats(User $user, int $matchId): array
    {
        // Check user access first
        if (! $this->hasUserAccessToMatch($user, $matchId)) {
            return [];
        }

        return [];
    }
}
