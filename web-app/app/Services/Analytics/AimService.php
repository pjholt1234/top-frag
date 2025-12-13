<?php

namespace App\Services\Analytics;

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
     * Get available weapons for filtered matches
     */
    public function getAvailableWeapons(User $user, array $filters): array
    {
        $cacheKey = $this->getCacheKey('weapons', $user, $filters);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user, $filters) {
            return $this->buildAvailableWeapons($user, $filters);
        });
    }

    /**
     * Get aggregated hit distribution data
     */
    public function getHitDistribution(User $user, array $filters, ?string $weaponName = null): array
    {
        $cacheKey = $this->getCacheKey('hit_dist_'.($weaponName ?? 'all'), $user, $filters);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user, $filters, $weaponName) {
            return $this->buildHitDistribution($user, $filters, $weaponName);
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
     * Build available weapons list
     */
    private function buildAvailableWeapons(User $user, array $filters): array
    {
        if (! $user->steam_id) {
            return [];
        }

        $matches = $this->getMatchesForPeriod($user, $filters);
        $matchIds = $matches->pluck('match_id')->unique();

        if ($matchIds->isEmpty()) {
            return [];
        }

        $weapons = \App\Models\PlayerMatchAimWeaponEvent::query()
            ->whereIn('match_id', $matchIds)
            ->where('player_steam_id', $user->steam_id)
            ->select('weapon_name')
            ->distinct()
            ->orderBy('weapon_name')
            ->get()
            ->map(function ($event) {
                return [
                    'value' => $event->weapon_name,
                    'label' => $this->getWeaponDisplayName($event->weapon_name),
                ];
            })
            ->toArray();

        // Add "All Weapons" option at the beginning
        array_unshift($weapons, [
            'value' => 'all',
            'label' => 'All Weapons',
        ]);

        return $weapons;
    }

    /**
     * Build aggregated hit distribution data
     */
    private function buildHitDistribution(User $user, array $filters, ?string $weaponName = null): array
    {
        if (! $user->steam_id) {
            return [];
        }

        $matches = $this->getMatchesForPeriod($user, $filters);
        $matchIds = $matches->pluck('match_id')->unique();

        if ($matchIds->isEmpty()) {
            return [];
        }

        // If weapon is specified and not 'all', get weapon-specific data
        if ($weaponName && $weaponName !== 'all') {
            return $this->aggregateWeaponHitDistribution($matchIds, $user->steam_id, $weaponName);
        }

        // Otherwise, get aggregated data from PlayerMatchAimEvent
        return $this->aggregateAllWeaponsHitDistribution($matchIds, $user->steam_id);
    }

    /**
     * Aggregate hit distribution for all weapons
     */
    private function aggregateAllWeaponsHitDistribution(Collection $matchIds, string $steamId): array
    {
        $aimEvents = PlayerMatchAimEvent::query()
            ->whereIn('match_id', $matchIds)
            ->where('player_steam_id', $steamId)
            ->selectRaw('
                SUM(shots_fired) as shots_fired,
                SUM(shots_hit) as shots_hit,
                SUM(head_hits_total) as head_hits_total,
                SUM(upper_chest_hits_total) as upper_chest_hits_total,
                SUM(chest_hits_total) as chest_hits_total,
                SUM(legs_hits_total) as legs_hits_total,
                SUM(spraying_shots_fired) as spraying_shots_fired,
                SUM(spraying_shots_hit) as spraying_shots_hit
            ')
            ->first();

        if (! $aimEvents || (int) $aimEvents->shots_fired == 0) {
            return [];
        }

        // Cast all values to ensure proper numeric types
        $shotsFired = (int) $aimEvents->shots_fired;
        $shotsHit = (int) $aimEvents->shots_hit;
        $headHits = (int) $aimEvents->head_hits_total;
        $upperChestHits = (int) $aimEvents->upper_chest_hits_total;
        $chestHits = (int) $aimEvents->chest_hits_total;
        $legsHits = (int) $aimEvents->legs_hits_total;
        $sprayingShotsFired = (int) $aimEvents->spraying_shots_fired;
        $sprayingShotsHit = (int) $aimEvents->spraying_shots_hit;

        $accuracy = $shotsHit > 0
            ? round(($shotsHit / $shotsFired) * 100, 1)
            : 0;

        $sprayAccuracy = $sprayingShotsFired > 0
            ? round(($sprayingShotsHit / $sprayingShotsFired) * 100, 1)
            : 0;

        $totalHits = $headHits + $upperChestHits + $chestHits + $legsHits;

        $headshotAccuracy = $totalHits > 0
            ? round(($headHits / $totalHits) * 100, 1)
            : 0;

        return [
            'shots_fired' => $shotsFired,
            'shots_hit' => $shotsHit,
            'accuracy_all_shots' => $accuracy,
            'headshot_accuracy' => $headshotAccuracy,
            'spraying_accuracy' => $sprayAccuracy,
            'head_hits_total' => $headHits,
            'upper_chest_hits_total' => $upperChestHits,
            'chest_hits_total' => $chestHits,
            'legs_hits_total' => $legsHits,
        ];
    }

    /**
     * Aggregate hit distribution for a specific weapon
     */
    private function aggregateWeaponHitDistribution(Collection $matchIds, string $steamId, string $weaponName): array
    {
        $weaponEvents = \App\Models\PlayerMatchAimWeaponEvent::query()
            ->whereIn('match_id', $matchIds)
            ->where('player_steam_id', $steamId)
            ->where('weapon_name', $weaponName)
            ->selectRaw('
                SUM(shots_fired) as shots_fired,
                SUM(shots_hit) as shots_hit,
                SUM(head_hits_total) as head_hits_total,
                SUM(upper_chest_hits_total) as upper_chest_hits_total,
                SUM(chest_hits_total) as chest_hits_total,
                SUM(legs_hits_total) as legs_hits_total,
                SUM(spraying_shots_fired) as spraying_shots_fired,
                SUM(spraying_shots_hit) as spraying_shots_hit
            ')
            ->first();

        if (! $weaponEvents || (int) $weaponEvents->shots_fired == 0) {
            return [];
        }

        // Cast all values to ensure proper numeric types
        $shotsFired = (int) $weaponEvents->shots_fired;
        $shotsHit = (int) $weaponEvents->shots_hit;
        $headHits = (int) $weaponEvents->head_hits_total;
        $upperChestHits = (int) $weaponEvents->upper_chest_hits_total;
        $chestHits = (int) $weaponEvents->chest_hits_total;
        $legsHits = (int) $weaponEvents->legs_hits_total;
        $sprayingShotsFired = (int) $weaponEvents->spraying_shots_fired;
        $sprayingShotsHit = (int) $weaponEvents->spraying_shots_hit;

        $accuracy = $shotsHit > 0
            ? round(($shotsHit / $shotsFired) * 100, 1)
            : 0;

        $sprayAccuracy = $sprayingShotsFired > 0
            ? round(($sprayingShotsHit / $sprayingShotsFired) * 100, 1)
            : 0;

        $totalHits = $headHits + $upperChestHits + $chestHits + $legsHits;

        $headshotAccuracy = $totalHits > 0
            ? round(($headHits / $totalHits) * 100, 1)
            : 0;

        return [
            'shots_fired' => $shotsFired,
            'shots_hit' => $shotsHit,
            'accuracy_all_shots' => $accuracy,
            'headshot_accuracy' => $headshotAccuracy,
            'spraying_accuracy' => $sprayAccuracy,
            'head_hits_total' => $headHits,
            'upper_chest_hits_total' => $upperChestHits,
            'chest_hits_total' => $chestHits,
            'legs_hits_total' => $legsHits,
        ];
    }

    /**
     * Get weapon display name
     */
    private function getWeaponDisplayName(string $weaponName): string
    {
        $weaponNames = [
            'ak47' => 'AK-47',
            'aug' => 'AUG',
            'awp' => 'AWP',
            'bizon' => 'PP-Bizon',
            'cz75a' => 'CZ75-Auto',
            'deagle' => 'Desert Eagle',
            'elite' => 'Dual Berettas',
            'famas' => 'FAMAS',
            'fiveseven' => 'Five-SeveN',
            'g3sg1' => 'G3SG1',
            'galilar' => 'Galil AR',
            'glock' => 'Glock-18',
            'hkp2000' => 'P2000',
            'm249' => 'M249',
            'm4a1' => 'M4A4',
            'm4a1_silencer' => 'M4A1-S',
            'mac10' => 'MAC-10',
            'mag7' => 'MAG-7',
            'mp5sd' => 'MP5-SD',
            'mp7' => 'MP7',
            'mp9' => 'MP9',
            'negev' => 'Negev',
            'nova' => 'Nova',
            'p250' => 'P250',
            'p90' => 'P90',
            'revolver' => 'R8 Revolver',
            'sawedoff' => 'Sawed-Off',
            'scar20' => 'SCAR-20',
            'sg556' => 'SG 553',
            'ssg08' => 'SSG 08',
            'tec9' => 'Tec-9',
            'ump45' => 'UMP-45',
            'usp_silencer' => 'USP-S',
            'xm1014' => 'XM1014',
        ];

        return $weaponNames[$weaponName] ?? ucfirst($weaponName);
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
