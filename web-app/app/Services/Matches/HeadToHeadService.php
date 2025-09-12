<?php

namespace App\Services\Matches;

use App\Models\User;

class HeadToHeadService
{
    use MatchAccessTrait;

    public function getHeadToHead(User $user, int $matchId, ?string $player1SteamId = null, ?string $player2SteamId = null): array
    {
        // Check user access first
        if (! $this->hasUserAccessToMatch($user, $matchId)) {
            return [];
        }

        // Return empty array for now - placeholder implementation
        return [];
    }

    private function getCacheKey(?string $player1SteamId, ?string $player2SteamId): string
    {
        $key = 'head-to-head';
        if ($player1SteamId) {
            $key .= "_player1_{$player1SteamId}";
        }
        if ($player2SteamId) {
            $key .= "_player2_{$player2SteamId}";
        }

        return $key;
    }
}
