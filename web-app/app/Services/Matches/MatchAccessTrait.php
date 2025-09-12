<?php

namespace App\Services\Matches;

use App\Models\User;

trait MatchAccessTrait
{
    private function hasUserAccessToMatch(User $user, int $matchId): bool
    {
        return $user->player?->matches()->where('matches.id', $matchId)->exists() ?? false;
    }
}
