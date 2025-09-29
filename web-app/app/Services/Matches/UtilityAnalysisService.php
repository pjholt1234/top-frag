<?php

namespace App\Services\Matches;

use App\Enums\GrenadeType;
use App\Models\GameMatch;
use App\Models\GrenadeEvent;
use App\Models\User;
use App\Services\MatchCacheManager;
use Illuminate\Support\Collection;

class UtilityAnalysisService
{
    use MatchAccessTrait;

    public function getAnalysis(User $user, int $matchId, ?string $playerSteamId = null, ?int $roundNumber = null): array
    {
        $cacheKey = $this->getCacheKey($playerSteamId, $roundNumber);

        return MatchCacheManager::remember($cacheKey, $matchId, function () use ($user, $matchId, $playerSteamId, $roundNumber) {
            return $this->buildAnalysis($user, $matchId, $playerSteamId, $roundNumber);
        });
    }

    private function getCacheKey(?string $playerSteamId, ?int $roundNumber): string
    {
        $key = 'utility-analysis';
        if ($playerSteamId) {
            $key .= "_player_{$playerSteamId}";
        }
        if ($roundNumber) {
            $key .= "_round_{$roundNumber}";
        }

        return $key;
    }

    private function buildAnalysis(User $user, int $matchId, ?string $playerSteamId = null, ?int $roundNumber = null): array
    {
        $match = GameMatch::find($matchId);

        if (! $match) {
            return [];
        }

        if (empty($playerSteamId)) {
            $playerSteamId = $this->getDefaultPlayerSteamId($match, $user);
        }

        $query = $match->grenadeEvents()->where('player_steam_id', $playerSteamId);

        if ($roundNumber) {
            $query->where('round_number', $roundNumber);
        }

        $grenadeEvents = $query->get();

        $playerRoundsEvents = $match->playerRoundEvents()
            ->where('player_steam_id', $playerSteamId)
            ->when($roundNumber, function ($query) use ($roundNumber) {
                return $query->where('round_number', $roundNumber);
            })
            ->get();

        return [
            'utility_usage' => $this->getUtilityUsageStats($grenadeEvents),
            'grenade_effectiveness' => $this->getGrenadeEffectivenessByRound($playerRoundsEvents),
            'grenade_timing' => $this->getGrenadeTimingData($grenadeEvents),
            'overall_stats' => $this->getOverallStats($grenadeEvents, $playerRoundsEvents),
            'players' => $this->getAvailablePlayers($match),
            'rounds' => $this->getAvailableRounds($match),
            'current_user_steam_id' => $user->steam_id,
        ];
    }

    private function getUtilityUsageStats(Collection $grenadeEvents): array
    {
        $groupedEvents = $grenadeEvents->groupBy(function (GrenadeEvent $event) {
            $type = $event->grenade_type;
            if ($type === GrenadeType::INCENDIARY || $type === GrenadeType::MOLOTOV) {
                return 'Fire';
            }

            return $type;
        });

        $usage = $groupedEvents->map(function (Collection $events, string $type) {
            return [
                'type' => $type,
                'count' => $events->count(),
                'percentage' => 0,
            ];
        })
            ->values()
            ->toArray();

        $total = $grenadeEvents->count();
        if ($total > 0) {
            foreach ($usage as &$item) {
                $item['percentage'] = round(($item['count'] / $total) * 100, 1);
            }
        }

        return $usage;
    }

    private function getGrenadeEffectivenessByRound(Collection $playerRoundEvents): array
    {
        return $playerRoundEvents->map(function ($roundEvent) {
            return [
                'round' => $roundEvent->round_number,
                'effectiveness' => round($roundEvent->grenade_effectiveness, 1) ?? 0,
                'total_grenades' => $roundEvent->grenades_thrown,
            ];
        })
            ->sortBy('round')
            ->values()
            ->toArray();
    }

    private function getGrenadeTimingData(Collection $grenadeEvents): array
    {
        // Group by grenade type, but combine Incendiary and Molotov into "Fire"
        $groupedEvents = $grenadeEvents->groupBy(function (GrenadeEvent $event) {
            $type = $event->grenade_type;
            if ($type === GrenadeType::INCENDIARY || $type === GrenadeType::MOLOTOV) {
                return 'Fire';
            }

            return $type;
        });

        return $groupedEvents->map(function (Collection $events, string $type) {
            return [
                'type' => $type,
                'timing_data' => $events->map(function (GrenadeEvent $event) {
                    return [
                        'round_time' => max(0, $event->round_time),
                        'round_number' => $event->round_number,
                        'effectiveness' => $event->effectiveness_rating ?? 0,
                    ];
                })->toArray(),
            ];
        })
            ->values()
            ->toArray();
    }

    private function getOverallStats(Collection $grenadeEvents, Collection $playerRoundsEvents): array
    {
        $flashEvents = $grenadeEvents->where('grenade_type', GrenadeType::FLASHBANG);
        $heEvents = $grenadeEvents->where('grenade_type', GrenadeType::HE_GRENADE);

        $count = 0;
        $effectivenessTotal = 0;

        foreach ($playerRoundsEvents as $playerRoundsEvent) {
            if ($playerRoundsEvent->grenade_effectiveness === null || $playerRoundsEvent->grenade_effectiveness == 0) {
                continue;
            }

            $count++;
            $effectivenessTotal += $playerRoundsEvent->grenade_effectiveness;
        }

        $averageGrenadeEffectiveness = 0;
        if ($count > 0) {
            $averageGrenadeEffectiveness = $effectivenessTotal / $count;
        }

        return [
            'overall_grenade_rating' => round($averageGrenadeEffectiveness, 1),
            'flash_stats' => $this->getFlashStats($flashEvents),
            'he_stats' => $this->getHeStats($heEvents),
            'smoke_stats' => $this->getSmokeStats($playerRoundsEvents),
        ];
    }

    private function getFlashStats(Collection $flashEvents): array
    {
        if ($flashEvents->isEmpty()) {
            return [
                'enemy_avg_duration' => 0,
                'friendly_avg_duration' => 0,
                'enemy_avg_blinded' => 0,
                'friendly_avg_blinded' => 0,
            ];
        }

        $enemyDurations = $flashEvents->where('enemy_flash_duration', '>', 0)
            ->pluck('enemy_flash_duration');
        $friendlyDurations = $flashEvents->where('friendly_flash_duration', '>', 0)
            ->pluck('friendly_flash_duration');
        $enemyBlinded = $flashEvents->pluck('enemy_players_affected');
        $friendlyBlinded = $flashEvents->pluck('friendly_players_affected');

        return [
            'enemy_avg_duration' => round($enemyDurations->avg() ?? 0, 2),
            'friendly_avg_duration' => round($friendlyDurations->avg() ?? 0, 2),
            'enemy_avg_blinded' => round($enemyBlinded->avg() ?? 0, 1),
            'friendly_avg_blinded' => round($friendlyBlinded->avg() ?? 0, 1),
        ];
    }

    private function getHeStats(Collection $heEvents): array
    {
        if ($heEvents->isEmpty()) {
            return ['avg_damage' => 0];
        }

        $damageEvents = $heEvents->where('damage_dealt', '>', 0);

        return [
            'avg_damage' => round($damageEvents->avg('damage_dealt') ?? 0, 1),
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

    private function getDefaultPlayerSteamId(GameMatch $match, User $user): string
    {
        if ($user->steam_id) {
            return $user->steam_id;
        } else {
            return $match->players->first()->steam_id;
        }
    }

    private function getAvailableRounds(GameMatch $match): array
    {
        $rounds = GrenadeEvent::where('match_id', $match->id)
            ->distinct()
            ->pluck('round_number')
            ->sort()
            ->values()
            ->toArray();

        return array_merge(['all'], $rounds);
    }

    private function getGrenadeCountForRound(int $roundNumber, Collection $grenadeEvents): int
    {
        return $grenadeEvents->where('round_number', $roundNumber)->count();
    }

    private function getSmokeStats(Collection $playerRoundsEvents): array
    {
        // Calculate total smoke blocking duration from round events
        $totalSmokeBlockingDuration = $playerRoundsEvents->sum('smoke_blocking_duration');

        // Calculate average smoke blocking duration per round
        $avgSmokeBlockingDuration = 0;
        $totalSmokesThrown = $playerRoundsEvents->sum('smokes_thrown');
        $roundsWithSmoke = $playerRoundsEvents->where('smokes_thrown', '>', 0);
        if ($roundsWithSmoke->count() > 0) {
            $avgSmokeBlockingDuration = $totalSmokeBlockingDuration / $totalSmokesThrown;
        }

        return [
            'total_smoke_blocking_duration' => $totalSmokeBlockingDuration,
            'avg_smoke_blocking_duration' => round($avgSmokeBlockingDuration, 1),
            'total_round_smoke_blocking_duration' => $totalSmokeBlockingDuration,
            'avg_round_smoke_blocking_duration' => round($avgSmokeBlockingDuration, 1),
            'smoke_count' => $totalSmokesThrown,
        ];
    }
}
