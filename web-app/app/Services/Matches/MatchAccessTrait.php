<?php

namespace App\Services\Matches;

use App\Models\User;

trait MatchAccessTrait
{
    private function hasUserAccessToMatch(User $user, int $matchId): bool
    {
        // Check if user participated in the match
        $participated = $user->player?->matches()->where('matches.id', $matchId)->exists() ?? false;

        // Check if user uploaded the match
        $uploaded = $user->uploadedGames()->where('matches.id', $matchId)->exists();

        return $participated || $uploaded;
    }
}
