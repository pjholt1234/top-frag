<?php

namespace App\Services\Matches;

use App\Models\GameMatch;
use App\Services\MatchCacheManager;

class TopRolePlayerService
{

    public function __construct(
        private PlayerComplexionService $playerComplexionService
    ) {}

    public function get(int $matchId): array
    {
        $cacheKey = $this->getCacheKey();

        return MatchCacheManager::remember($cacheKey, $matchId, function () use ($matchId) {
            return $this->buildTopRolePlayers($matchId);
        });
    }

    private function getCacheKey(): string
    {
        return 'top-role-players';
    }

    private function buildTopRolePlayers(int $matchId): array
    {
        $match = GameMatch::find($matchId);
        if (!$match) {
            return [
                'opener' => [
                    'name' => null,
                    'steam_id' => null,
                    'score' => 0,
                ],
                'closer' => [
                    'name' => null,
                    'steam_id' => null,
                    'score' => 0,
                ],
                'support' => [
                    'name' => null,
                    'steam_id' => null,
                    'score' => 0,
                ],
                'fragger' => [
                    'name' => null,
                    'steam_id' => null,
                    'score' => 0,
                ],
            ];
        }

        $players = $this->getAvailablePlayers($match);
        $playerComplexions = [];

        // Get complexion data for all players
        foreach ($players as $player) {
            $complexion = $this->playerComplexionService->get($player['steam_id'], $matchId);
            if (!empty($complexion)) {
                $playerComplexions[] = [
                    'steam_id' => $player['steam_id'],
                    'name' => $player['name'],
                    'complexion' => $complexion,
                ];
            }
        }

        if (empty($playerComplexions)) {
            return [
                'opener' => [
                    'name' => null,
                    'steam_id' => null,
                    'score' => 0,
                ],
                'closer' => [
                    'name' => null,
                    'steam_id' => null,
                    'score' => 0,
                ],
                'support' => [
                    'name' => null,
                    'steam_id' => null,
                    'score' => 0,
                ],
                'fragger' => [
                    'name' => null,
                    'steam_id' => null,
                    'score' => 0,
                ],
            ];
        }

        // Find the best player in each role
        return [
            'opener' => $this->getTopPlayerInRole($playerComplexions, 'opener'),
            'closer' => $this->getTopPlayerInRole($playerComplexions, 'closer'),
            'support' => $this->getTopPlayerInRole($playerComplexions, 'support'),
            'fragger' => $this->getTopPlayerInRole($playerComplexions, 'fragger'),
        ];
    }

    private function getTopPlayerInRole(array $playerComplexions, string $role): array
    {
        $topPlayer = null;
        $topScore = -1;

        foreach ($playerComplexions as $player) {
            $score = $player['complexion'][$role] ?? 0;
            if ($score > $topScore) {
                $topScore = $score;
                $topPlayer = $player;
            }
        }

        if (!$topPlayer) {
            return [
                'name' => null,
                'steam_id' => null,
                'score' => 0,
            ];
        }

        return [
            'name' => $topPlayer['name'],
            'steam_id' => $topPlayer['steam_id'],
            'score' => $topScore,
        ];
    }

    private function getAvailablePlayers(GameMatch $match): array
    {
        return $match->players->map(function ($player) {
            return [
                'steam_id' => $player->steam_id,
                'name' => $player->name,
            ];
        })->toArray();
    }
}
