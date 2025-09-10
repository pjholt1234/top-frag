<?php

namespace App\Services;

use App\Enums\GrenadeType;
use App\Models\GameMatch;
use App\Models\GrenadeEvent;
use App\Models\User;
use Illuminate\Support\Collection;

class MatchUtilityAnalysisService
{
    public function getUtilityAnalysis(User $user, int $matchId, ?string $playerSteamId = null, ?int $roundNumber = null): array
    {
        $match = $this->getMatchForUser($user, $matchId);
        if (! $match) {
            return [];
        }

        $query = GrenadeEvent::where('match_id', $matchId);

        if ($playerSteamId) {
            $query->where('player_steam_id', $playerSteamId);
        }

        if ($roundNumber) {
            $query->where('round_number', $roundNumber);
        }

        $grenadeEvents = $query->get();

        if ($grenadeEvents->isEmpty()) {
            return [];
        }

        return [
            'utility_usage' => $this->getUtilityUsageStats($grenadeEvents),
            'grenade_effectiveness' => $this->getGrenadeEffectivenessByRound($grenadeEvents),
            'grenade_timing' => $this->getGrenadeTimingData($grenadeEvents),
            'overall_stats' => $this->getOverallStats($grenadeEvents),
            'players' => $this->getAvailablePlayers($match),
            'rounds' => $this->getAvailableRounds($match),
            'current_user_steam_id' => $user->steam_id,
        ];
    }

    private function getMatchForUser(User $user, int $matchId): ?GameMatch
    {
        if (! $user->player) {
            return null;
        }

        return $user->player->matches()
            ->where('matches.id', $matchId)
            ->first();
    }

    private function getUtilityUsageStats(Collection $grenadeEvents): array
    {
        // Group by grenade type, but combine Incendiary and Molotov into "Fire"
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
                'percentage' => 0, // Will be calculated after total
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

    private function getGrenadeEffectivenessByRound(Collection $grenadeEvents): array
    {
        return $grenadeEvents->groupBy('round_number')
            ->map(function (Collection $events, int $round) {
                $totalEffectiveness = $events->where('effectiveness_rating', '>', 0)
                    ->avg('effectiveness_rating') ?? 0;

                return [
                    'round' => $round,
                    'effectiveness' => round($totalEffectiveness, 1),
                    'total_grenades' => $events->count(),
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
                        'round_time' => max(0, $event->round_time), // Ensure round_time is never negative
                        'round_number' => $event->round_number,
                        'effectiveness' => $event->effectiveness_rating ?? 0,
                    ];
                })->toArray(),
            ];
        })
            ->values()
            ->toArray();
    }

    private function getOverallStats(Collection $grenadeEvents): array
    {
        $flashEvents = $grenadeEvents->where('grenade_type', GrenadeType::FLASHBANG);
        $heEvents = $grenadeEvents->where('grenade_type', GrenadeType::HE_GRENADE);

        return [
            'overall_grenade_rating' => $this->calculateOverallRating($grenadeEvents),
            'flash_stats' => $this->getFlashStats($flashEvents),
            'he_stats' => $this->getHeStats($heEvents),
        ];
    }

    private function calculateOverallRating(Collection $grenadeEvents): float
    {
        $ratedEvents = $grenadeEvents->where('effectiveness_rating', '>', 0);

        if ($ratedEvents->isEmpty()) {
            return 0.0;
        }

        return round($ratedEvents->avg('effectiveness_rating'), 1);
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
}
