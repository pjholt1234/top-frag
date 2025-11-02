<?php

namespace App\Services;

use App\Models\PlayerMatchAimEvent;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class AimService
{
    private const CACHE_TTL = 900; // 15 minutes

    /**
     * Get aim stats with filters and trends
     */
    public function getAimStats(User $user, array $filters): array
    {
        $cacheKey = $this->getCacheKey('aim', $user, $filters);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user, $filters) {
            return $this->buildAimStats($user, $filters);
        });
    }

    /**
     * Invalidate cache for a user
     */
    public function invalidateUserCache(string $steamId): void
    {
        Cache::forget("aim:*:{$steamId}:*");
    }

    /**
     * Build aim stats section
     */
    private function buildAimStats(User $user, array $filters): array
    {
        $currentMatches = $this->getMatchesForPeriod($user, $filters);
        $previousMatches = $this->getMatchesForPeriod($user, $filters, true);

        $currentStats = $this->aggregateAimStats($currentMatches);
        $previousStats = $this->aggregateAimStats($previousMatches);

        return [
            'aim_statistics' => [
                'average_aim_rating' => $this->buildStatWithTrend(
                    $currentStats['aim_rating'],
                    $previousStats['aim_rating']
                ),
                'average_headshot_percentage' => $this->buildStatWithTrend(
                    $currentStats['headshot_percentage'],
                    $previousStats['headshot_percentage']
                ),
                'average_spray_accuracy' => $this->buildStatWithTrend(
                    $currentStats['spray_accuracy'],
                    $previousStats['spray_accuracy']
                ),
                'average_crosshair_placement' => $this->buildStatWithTrend(
                    $currentStats['crosshair_placement'],
                    $previousStats['crosshair_placement'],
                    true
                ),
                'average_time_to_damage' => $this->buildStatWithTrend(
                    $currentStats['time_to_damage'],
                    $previousStats['time_to_damage'],
                    true
                ),
            ],
            'weapon_breakdown' => $currentStats['weapon_breakdown'],
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
     * Aggregate aim stats from matches
     */
    private function aggregateAimStats(Collection $matches): array
    {
        if ($matches->isEmpty()) {
            return $this->getEmptyAimStats();
        }

        $matchIds = $matches->pluck('match_id')->unique();
        $steamId = $matches->first()->player_steam_id;

        $aimEvents = PlayerMatchAimEvent::query()
            ->whereIn('match_id', $matchIds)
            ->where('player_steam_id', $steamId)
            ->get();

        if ($aimEvents->isEmpty()) {
            return $this->getEmptyAimStats();
        }

        // Calculate crosshair placement in degrees using Pythagorean theorem (magnitude of displacement)
        $avgX = $aimEvents->avg('average_crosshair_placement_x');
        $avgY = $aimEvents->avg('average_crosshair_placement_y');
        $crosshairPlacement = round(sqrt(pow($avgX, 2) + pow($avgY, 2)), 1);

        return [
            'aim_rating' => round($aimEvents->avg('aim_rating'), 1),
            'headshot_percentage' => round($aimEvents->avg('headshot_accuracy'), 1),
            'spray_accuracy' => round($aimEvents->avg('spraying_accuracy'), 1),
            'crosshair_placement' => $crosshairPlacement,
            'time_to_damage' => round($aimEvents->avg('average_time_to_damage'), 0),
            'weapon_breakdown' => [], // TODO: Implement weapon breakdown
        ];
    }

    /**
     * Get empty aim stats structure
     */
    private function getEmptyAimStats(): array
    {
        return [
            'aim_rating' => 0,
            'headshot_percentage' => 0,
            'spray_accuracy' => 0,
            'crosshair_placement' => 0,
            'time_to_damage' => 0,
            'weapon_breakdown' => [],
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

        return "aim:{$tab}:{$user->steam_id}:{$filterHash}";
    }
}
