<?php

namespace App\Services;

use App\Models\Player;
use App\Models\PlayerRank;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class RanksService
{
    private const CACHE_TTL = 900; // 15 minutes

    /**
     * Get rank stats with filters
     */
    public function getRankStats(User $user, array $filters): array
    {
        $cacheKey = $this->getCacheKey('rank-stats', $user, $filters);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user, $filters) {
            return $this->buildRankStats($user, $filters);
        });
    }

    /**
     * Invalidate cache for a user
     */
    public function invalidateUserCache(string $steamId): void
    {
        Cache::forget("ranks:*:{$steamId}:*");
    }

    /**
     * Build rank stats section
     */
    private function buildRankStats(User $user, array $filters): array
    {
        if (! $user->steam_id) {
            return [
                'competitive' => [],
                'premier' => [],
                'faceit' => [],
            ];
        }

        // Get player record
        $player = Player::where('steam_id', $user->steam_id)->first();

        if (! $player) {
            return [
                'competitive' => [],
                'premier' => [],
                'faceit' => [],
            ];
        }

        // Build query for rank data based on filters
        $query = PlayerRank::query()
            ->where('player_id', $player->id)
            ->orderBy('created_at', 'desc');

        // Apply date filters
        if (! empty($filters['date_from']) && ! empty($filters['date_to'])) {
            $query->whereBetween('created_at', [$filters['date_from'], $filters['date_to']]);
        }

        // Apply match count limit
        $matchCount = $filters['past_match_count'] ?? 10;
        $query->take($matchCount * 10); // Get enough records for all rank types and maps

        // Get all rank records
        $ranks = $query->get();

        // Group by rank type
        $competitive = $ranks->where('rank_type', 'competitive')->values();
        $premier = $ranks->where('rank_type', 'premier')->values();
        $faceit = $ranks->where('rank_type', 'faceit')->values();

        return [
            'competitive' => $this->formatRankHistory($competitive, 'competitive', $filters),
            'premier' => $this->formatRankHistory($premier, 'premier', $filters),
            'faceit' => $this->formatRankHistory($faceit, 'faceit', $filters),
        ];
    }

    /**
     * Format rank history for display
     */
    private function formatRankHistory(Collection $ranks, string $rankType, array $filters): array
    {
        if ($ranks->isEmpty()) {
            return [
                'rank_type' => $rankType,
                'current_rank' => null,
                'current_rank_value' => null,
                'history' => [],
                'trend' => 'neutral',
                'maps' => [],
            ];
        }

        $matchCount = $filters['past_match_count'] ?? 10;

        // For competitive, group by map
        if ($rankType === 'competitive') {
            $mapGroups = $ranks->groupBy('map');
            $maps = [];

            foreach ($mapGroups as $map => $mapRanks) {
                // Sort by date (oldest first for chronological graph), then limit
                $sortedRanks = $mapRanks->sortBy('created_at')->values()->take($matchCount);
                $currentRank = $sortedRanks->last();
                $previousRank = $sortedRanks->count() > 1 ? $sortedRanks->get($sortedRanks->count() - 2) : null;

                $trend = 'neutral';
                if ($previousRank) {
                    if ($currentRank->rank_value > $previousRank->rank_value) {
                        $trend = 'up';
                    } elseif ($currentRank->rank_value < $previousRank->rank_value) {
                        $trend = 'down';
                    }
                }

                $maps[] = [
                    'map' => $map,
                    'current_rank' => $currentRank->rank,
                    'current_rank_value' => $currentRank->rank_value,
                    'trend' => $trend,
                    'history' => $sortedRanks->map(function ($rank) {
                        return [
                            'rank' => $rank->rank,
                            'rank_value' => $rank->rank_value,
                            'date' => $rank->created_at->format('Y-m-d'),
                            'timestamp' => $rank->created_at->timestamp,
                        ];
                    })->toArray(),
                ];
            }

            return [
                'rank_type' => $rankType,
                'maps' => $maps,
            ];
        }

        // For premier/faceit (non-map-specific)
        // Sort by date (oldest first for chronological graph), then limit
        $sortedRanks = $ranks->sortBy('created_at')->values()->take($matchCount);
        $currentRank = $sortedRanks->last();
        $previousRank = $sortedRanks->count() > 1 ? $sortedRanks->get($sortedRanks->count() - 2) : null;

        $trend = 'neutral';
        if ($previousRank) {
            if ($currentRank->rank_value > $previousRank->rank_value) {
                $trend = 'up';
            } elseif ($currentRank->rank_value < $previousRank->rank_value) {
                $trend = 'down';
            }
        }

        return [
            'rank_type' => $rankType,
            'current_rank' => $currentRank->rank,
            'current_rank_value' => $currentRank->rank_value,
            'trend' => $trend,
            'history' => $sortedRanks->map(function ($rank) {
                return [
                    'rank' => $rank->rank,
                    'rank_value' => $rank->rank_value,
                    'date' => $rank->created_at->format('Y-m-d'),
                    'timestamp' => $rank->created_at->timestamp,
                ];
            })->toArray(),
        ];
    }

    /**
     * Generate cache key
     */
    private function getCacheKey(string $tab, User $user, array $filters): string
    {
        $filterHash = md5(json_encode($filters));

        return "ranks:{$tab}:{$user->steam_id}:{$filterHash}";
    }
}
