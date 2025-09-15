<?php

namespace App\Services\Matches;

use App\Models\GameMatch;
use App\Models\PlayerMatchEvent;
use App\Models\User;
use App\Services\MatchCacheManager;

class PlayerStatsService
{
    use MatchAccessTrait;

    public function __construct(
        private readonly PlayerComplexionService $playerComplexionService
    ) {}

    public function get(User $user, array $filters, int $matchId): array
    {
        $cacheKey = $this->getCacheKey($filters);

        return MatchCacheManager::remember($cacheKey, $matchId, function () use ($user, $filters, $matchId) {
            return $this->buildStats($user, $filters, $matchId);
        });
    }

    private function getCacheKey(array $filters): string
    {
        $filterHash = empty($filters) ? 'default' : md5(serialize($filters));

        return "player-stats_{$filterHash}";
    }

    private function buildStats(User $user, array $filters, int $matchId): array
    {
        $match = GameMatch::find($matchId);

        if (empty($filters['player_steam_id'])) {
            return [
                'players' => $this->getAvailablePlayers($match),
                'current_user_steam_id' => $user->steam_id,
            ];
        }

        $playerMatchEvent = PlayerMatchEvent::query()
            ->where('match_id', $matchId)
            ->where('player_steam_id', $filters['player_steam_id'])
            ->first();

        if (! $playerMatchEvent) {
            return [];
        }

        return [
            'player_complexion' => $this->playerComplexionService->get($filters['player_steam_id'], $matchId),
            'trades' => $this->getPlayerTradeStats($playerMatchEvent),
            'clutch_stats' => $this->getPlayerClutchStats($playerMatchEvent),
            'deep_dive' => $this->getDeepDiveStats($playerMatchEvent),
            'players' => $this->getAvailablePlayers($match),
            'current_user_steam_id' => $user->steam_id,
        ];
    }

    private function getPlayerTradeStats(PlayerMatchEvent $playerMatchEvent): array
    {
        return [
            'total_successful_trades' => $playerMatchEvent->total_successful_trades,
            'total_possible_trades' => $playerMatchEvent->total_possible_trades,
            'total_traded_deaths' => $playerMatchEvent->total_traded_deaths,
            'total_possible_traded_deaths' => $playerMatchEvent->total_possible_traded_deaths,
        ];
    }

    private function getDeepDiveStats(PlayerMatchEvent $playerMatchEvent): array
    {
        return [
            'round_swing' => 0,
            'impact' => 0,
            'opening_duels' => [
                'first_kills' => $playerMatchEvent->first_kills,
                'first_deaths' => $playerMatchEvent->first_deaths,
            ],
        ];
    }

    private function getPlayerClutchStats(PlayerMatchEvent $playerMatchEvent): array
    {
        return [
            '1v1' => [
                'clutch_wins_1v1' => $playerMatchEvent->clutch_wins_1v1,
                'clutch_attempts_1v1' => $playerMatchEvent->clutch_attempts_1v1,
                'clutch_win_percentage_1v1' => calculatePercentage($playerMatchEvent->clutch_wins_1v1, $playerMatchEvent->clutch_attempts_1v1),
            ],
            '1v2' => [
                'clutch_wins_1v2' => $playerMatchEvent->clutch_wins_1v2,
                'clutch_attempts_1v2' => $playerMatchEvent->clutch_attempts_1v2,
                'clutch_win_percentage_1v2' => calculatePercentage($playerMatchEvent->clutch_wins_1v2, $playerMatchEvent->clutch_attempts_1v2),
            ],
            '1v3' => [
                'clutch_wins_1v3' => $playerMatchEvent->clutch_wins_1v3,
                'clutch_attempts_1v3' => $playerMatchEvent->clutch_attempts_1v3,
                'clutch_win_percentage_1v3' => calculatePercentage($playerMatchEvent->clutch_wins_1v3, $playerMatchEvent->clutch_attempts_1v3),
            ],
            '1v4' => [
                'clutch_wins_1v4' => $playerMatchEvent->clutch_wins_1v4,
                'clutch_attempts_1v4' => $playerMatchEvent->clutch_attempts_1v4,
                'clutch_win_percentage_1v4' => calculatePercentage($playerMatchEvent->clutch_wins_1v4, $playerMatchEvent->clutch_attempts_1v4),
            ],
            '1v5' => [
                'clutch_wins_1v5' => $playerMatchEvent->clutch_wins_1v5,
                'clutch_attempts_1v5' => $playerMatchEvent->clutch_attempts_1v5,
                'clutch_win_percentage_1v5' => calculatePercentage($playerMatchEvent->clutch_wins_1v5, $playerMatchEvent->clutch_attempts_1v5),
            ],
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
