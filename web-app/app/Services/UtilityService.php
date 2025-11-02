<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class UtilityService
{
    private const CACHE_TTL = 900; // 15 minutes

    /**
     * Get utility stats with filters and trends
     */
    public function getUtilityStats(User $user, array $filters): array
    {
        $cacheKey = $this->getCacheKey('utility', $user, $filters);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user, $filters) {
            return $this->buildUtilityStats($user, $filters);
        });
    }

    /**
     * Invalidate cache for a user
     */
    public function invalidateUserCache(string $steamId): void
    {
        Cache::forget("utility:*:{$steamId}:*");
    }

    /**
     * Build utility stats section
     */
    private function buildUtilityStats(User $user, array $filters): array
    {
        $currentMatches = $this->getMatchesForPeriod($user, $filters);
        $previousMatches = $this->getMatchesForPeriod($user, $filters, true);

        $currentStats = $this->aggregateUtilityStats($currentMatches);
        $previousStats = $this->aggregateUtilityStats($previousMatches);

        return [
            'avg_blind_duration_enemy' => $this->buildStatWithTrend(
                $currentStats['enemy_flash_duration'],
                $previousStats['enemy_flash_duration']
            ),
            'avg_blind_duration_friendly' => $this->buildStatWithTrend(
                $currentStats['friendly_flash_duration'],
                $previousStats['friendly_flash_duration'],
                true
            ),
            'avg_players_blinded_enemy' => $this->buildStatWithTrend(
                $currentStats['enemy_players_blinded'],
                $previousStats['enemy_players_blinded']
            ),
            'avg_players_blinded_friendly' => $this->buildStatWithTrend(
                $currentStats['friendly_players_blinded'],
                $previousStats['friendly_players_blinded'],
                true
            ),
            'he_molotov_damage' => $this->buildStatWithTrend(
                $currentStats['he_molotov_damage'],
                $previousStats['he_molotov_damage']
            ),
            'grenade_effectiveness' => $this->buildStatWithTrend(
                $currentStats['grenade_effectiveness'],
                $previousStats['grenade_effectiveness']
            ),
            'average_grenade_usage' => $this->buildStatWithTrend(
                $currentStats['grenade_usage'],
                $previousStats['grenade_usage']
            ),
        ];
    }

    /**
     * Get matches for a given period with filters
     */
    private function getMatchesForPeriod(User $user, array $filters, bool $previousPeriod = false): Collection
    {
        if (! $user->steam_id) {
            return collect([]);
        }

        $matchCount = $filters['past_match_count'] ?? 10;
        $offset = $previousPeriod ? $matchCount : 0;

        $query = \App\Models\PlayerMatchEvent::query()
            ->select(
                'player_match_events.*',
                'matches.id as match_id',
                'matches.map',
                'matches.match_type',
                'matches.created_at'
            )
            ->join('matches', 'player_match_events.match_id', '=', 'matches.id')
            ->where('player_match_events.player_steam_id', $user->steam_id)
            ->orderBy('matches.created_at', 'desc');

        // Apply filters
        if (! empty($filters['date_from']) && ! empty($filters['date_to'])) {
            $query->whereBetween('matches.created_at', [$filters['date_from'], $filters['date_to']]);
        }

        if (! empty($filters['game_type'])) {
            $query->where('matches.match_type', $filters['game_type']);
        }

        if (! empty($filters['map'])) {
            $query->where('matches.map', $filters['map']);
        }

        return $query->skip($offset)->take($matchCount)->get();
    }

    /**
     * Aggregate utility stats from matches
     */
    private function aggregateUtilityStats(Collection $matches): array
    {
        if ($matches->isEmpty()) {
            return [
                'enemy_flash_duration' => 0,
                'friendly_flash_duration' => 0,
                'enemy_players_blinded' => 0,
                'friendly_players_blinded' => 0,
                'he_molotov_damage' => 0,
                'grenade_effectiveness' => 0,
                'grenade_usage' => 0,
            ];
        }

        return [
            'enemy_flash_duration' => round($matches->avg('enemy_flash_duration'), 2),
            'friendly_flash_duration' => round($matches->avg('friendly_flash_duration'), 2),
            'enemy_players_blinded' => round($matches->avg('enemy_players_affected'), 1),
            'friendly_players_blinded' => round($matches->avg('friendly_players_affected'), 1),
            'he_molotov_damage' => round($matches->avg('damage_dealt'), 1),
            'grenade_effectiveness' => round($matches->avg('average_grenade_effectiveness'), 1),
            'grenade_usage' => round($matches->avg(function ($match) {
                return $match->flashes_thrown + $match->fire_grenades_thrown +
                    $match->smokes_thrown + $match->hes_thrown + $match->decoys_thrown;
            }), 1),
        ];
    }

    /**
     * Build a stat object with trend information
     *
     * @param  float  $current  Current period value
     * @param  float  $previous  Previous period value
     * @param  bool  $lowerIsBetter  If true, lower values are considered better (reversed trend logic)
     */
    private function buildStatWithTrend($current, $previous, bool $lowerIsBetter = false): array
    {
        $trend = 'neutral';
        $change = 0;

        if ($previous > 0) {
            $change = round((($current - $previous) / $previous) * 100, 1);

            if ($change > 0) {
                $trend = $lowerIsBetter ? 'down' : 'up';
            } elseif ($change < 0) {
                $trend = $lowerIsBetter ? 'up' : 'down';
            }
        } elseif ($current > 0 && $previous == 0) {
            $trend = $lowerIsBetter ? 'down' : 'up';
            $change = 100;
        }

        return [
            'value' => $current,
            'trend' => $trend,
            'change' => abs($change),
        ];
    }

    /**
     * Generate cache key
     */
    private function getCacheKey(string $tab, User $user, array $filters): string
    {
        $filterHash = md5(json_encode($filters));

        return "utility:{$tab}:{$user->steam_id}:{$filterHash}";
    }
}
