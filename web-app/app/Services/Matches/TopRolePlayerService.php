<?php

namespace App\Services\Matches;

use App\Models\GameMatch;
use App\Models\PlayerMatchEvent;
use App\Services\MatchCacheManager;

class TopRolePlayerService
{
    private int $currentMatchId;

    public function __construct(
        private PlayerComplexionService $playerComplexionService
    ) {}

    public function get(int $matchId): array
    {
        $this->currentMatchId = $matchId;
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
        if (! $match) {
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
            if (! empty($complexion)) {
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

        if (! $topPlayer) {
            return [
                'name' => null,
                'steam_id' => null,
                'score' => 0,
                'stats' => [],
            ];
        }

        return [
            'name' => $topPlayer['name'],
            'steam_id' => $topPlayer['steam_id'],
            'score' => $topScore,
            'stats' => $this->getRoleStats($topPlayer['steam_id'], $role),
        ];
    }

    private function getRoleStats(string $playerSteamId, string $role): array
    {
        $playerMatchEvent = PlayerMatchEvent::query()
            ->where('match_id', $this->currentMatchId)
            ->where('player_steam_id', $playerSteamId)
            ->first();

        if (! $playerMatchEvent) {
            return [];
        }

        switch ($role) {
            case 'opener':
                return [
                    'First Kills' => $playerMatchEvent->first_kills,
                    'First Deaths' => $playerMatchEvent->first_deaths,
                    'Avg Time to Contact' => round($playerMatchEvent->average_time_to_contact, 1).'s',
                    'Avg Time of Death' => round($playerMatchEvent->average_round_time_of_death, 1).'s',
                    'Trade Success Rate' => round(calculatePercentage($playerMatchEvent->total_successful_trades, $playerMatchEvent->total_possible_traded_deaths), 1).'%',
                ];
            case 'closer':
                return [
                    'Clutch Wins' => $playerMatchEvent->clutch_wins,
                    'Clutch Attempts' => $playerMatchEvent->clutch_attempts,
                    'Clutch Win Rate' => round($playerMatchEvent->clutch_win_percentage, 1).'%',
                    'Avg Time to Contact' => round($playerMatchEvent->average_time_to_contact, 1).'s',
                    'Avg Time of Death' => round($playerMatchEvent->average_round_time_of_death, 1).'s',
                ];
            case 'support':
                return [
                    'Grenades Thrown' => $playerMatchEvent->grenades_thrown,
                    'Damage from Grenades' => $playerMatchEvent->damage_dealt,
                    'Enemy Flash Duration' => round($playerMatchEvent->enemy_flash_duration, 1).'s',
                    'Grenade Effectiveness' => round($playerMatchEvent->average_grenade_effectiveness, 1).'%',
                    'Flashes Leading to Kills' => $playerMatchEvent->flashes_leading_to_kills,
                ];
            case 'fragger':
                return [
                    'Kills' => $playerMatchEvent->kills,
                    'Deaths' => $playerMatchEvent->deaths,
                    'K/D Ratio' => round($playerMatchEvent->kills / max($playerMatchEvent->deaths, 1), 2),
                    'ADR' => round($playerMatchEvent->adr),
                    'Trade Success Rate' => round(calculatePercentage($playerMatchEvent->total_successful_trades, $playerMatchEvent->total_possible_trades), 1).'%',
                ];
            default:
                return [];
        }
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
