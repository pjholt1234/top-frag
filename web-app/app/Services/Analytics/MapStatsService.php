<?php

namespace App\Services\Analytics;

use App\Models\GameMatch;
use App\Models\Player;
use App\Models\User;
use App\Services\Matches\PlayerComplexionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class MapStatsService
{
    private const CACHE_TTL = 900; // 15 minutes

    /**
     * Cache for win status to avoid N+1 queries
     */
    private array $winStatusCache = [];

    public function __construct(
        private readonly PlayerComplexionService $playerComplexionService
    ) {}

    /**
     * Get map stats with filters
     */
    public function getMapStats(User $user, array $filters): array
    {
        $cacheKey = $this->getCacheKey('map-stats', $user, $filters);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user, $filters) {
            return $this->buildMapStats($user, $filters);
        });
    }

    /**
     * Invalidate cache for a user
     */
    public function invalidateUserCache(string $steamId): void
    {
        Cache::forget("map-stats:*:{$steamId}:*");
    }

    /**
     * Build map stats section
     */
    private function buildMapStats(User $user, array $filters): array
    {
        $currentMatches = $this->getMatchesForPeriod($user, $filters);

        if ($currentMatches->isEmpty()) {
            return [
                'maps' => [],
                'total_matches' => 0,
            ];
        }

        // Preload win status to avoid N+1 queries
        $this->preloadWinStatus($currentMatches);

        // Group matches by map
        $mapGroups = $currentMatches->groupBy('map');

        $mapStats = $mapGroups->map(function ($mapMatches, $map) use ($user) {
            $matchCount = $mapMatches->count();

            // Calculate wins
            $wins = $mapMatches->filter(function ($match) {
                return $this->didPlayerWinMatch($match->match_id, $match->player_steam_id);
            })->count();

            // Calculate averages
            $totalKills = $mapMatches->sum('kills');
            $totalAssists = $mapMatches->sum('assists');
            $totalDeaths = $mapMatches->sum('deaths');
            $totalAdr = $mapMatches->sum('adr');
            $totalOpeningKills = $mapMatches->sum('first_kills');
            $totalOpeningDeaths = $mapMatches->sum('first_deaths');

            // Calculate player complexion for this map
            $complexion = $this->getPlayerComplexion($user, $mapMatches);

            return [
                'map' => $map,
                'matches' => $matchCount,
                'wins' => $wins,
                'win_rate' => $matchCount > 0 ? round(($wins / $matchCount) * 100, 1) : 0,
                'avg_kills' => $matchCount > 0 ? round($totalKills / $matchCount, 1) : 0,
                'avg_assists' => $matchCount > 0 ? round($totalAssists / $matchCount, 1) : 0,
                'avg_deaths' => $matchCount > 0 ? round($totalDeaths / $matchCount, 1) : 0,
                'avg_kd' => $totalDeaths > 0 ? round($totalKills / $totalDeaths, 2) : 0,
                'avg_adr' => $matchCount > 0 ? round($totalAdr / $matchCount, 1) : 0,
                'avg_opening_kills' => $matchCount > 0 ? round($totalOpeningKills / $matchCount, 1) : 0,
                'avg_opening_deaths' => $matchCount > 0 ? round($totalOpeningDeaths / $matchCount, 1) : 0,
                'avg_complexion' => $complexion,
            ];
        })->values()->sortByDesc('matches')->values()->toArray();

        return [
            'maps' => $mapStats,
            'total_matches' => $currentMatches->count(),
        ];
    }

    /**
     * Get matches for a given period with filters
     */
    private function getMatchesForPeriod(User $user, array $filters): Collection
    {
        if (! $user->steam_id) {
            return collect([]);
        }

        $matchCount = $filters['past_match_count'] ?? 10;

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

        return $query->take($matchCount)->get();
    }

    /**
     * Preload win status for a collection of matches to avoid N+1 queries
     */
    private function preloadWinStatus(Collection $matches): void
    {
        if ($matches->isEmpty()) {
            return;
        }

        $matchIds = $matches->pluck('match_id')->unique();
        $playerSteamId = $matches->first()->player_steam_id;

        // First, get the player's integer ID from their steam_id
        $player = Player::where('steam_id', $playerSteamId)->first();

        if (! $player) {
            return;
        }

        // Load all matches with their match players in one query
        $matchesData = GameMatch::query()
            ->whereIn('id', $matchIds)
            ->with(['matchPlayers' => function ($query) use ($player) {
                $query->where('player_id', $player->id);
            }])
            ->get()
            ->keyBy('id');

        // Build cache of win statuses
        foreach ($matchesData as $matchId => $match) {
            $matchPlayer = $match->matchPlayers->first();
            $cacheKey = "{$matchId}:{$playerSteamId}";

            if ($matchPlayer && $match->winning_team) {
                $this->winStatusCache[$cacheKey] = $match->winning_team === $matchPlayer->team->value;
            } else {
                $this->winStatusCache[$cacheKey] = false;
            }
        }
    }

    /**
     * Check if a player won a match by comparing their team with the winning team
     */
    private function didPlayerWinMatch(int $matchId, string $playerSteamId): bool
    {
        $cacheKey = "{$matchId}:{$playerSteamId}";

        // Return cached result if available
        if (isset($this->winStatusCache[$cacheKey])) {
            return $this->winStatusCache[$cacheKey];
        }

        // Fallback to direct query if not cached (shouldn't happen in normal flow)
        $match = GameMatch::find($matchId);

        if (! $match) {
            $this->winStatusCache[$cacheKey] = false;

            return false;
        }

        // Get the player's integer ID from their steam_id
        $player = Player::where('steam_id', $playerSteamId)->first();

        if (! $player) {
            $this->winStatusCache[$cacheKey] = false;

            return false;
        }

        $matchPlayer = $match->matchPlayers()
            ->where('player_id', $player->id)
            ->first();

        if (! $matchPlayer || ! $match->winning_team) {
            $this->winStatusCache[$cacheKey] = false;

            return false;
        }

        $won = $match->winning_team === $matchPlayer->team->value;
        $this->winStatusCache[$cacheKey] = $won;

        return $won;
    }

    /**
     * Get player complexion (role scores) across matches
     */
    private function getPlayerComplexion(User $user, Collection $matches): array
    {
        if ($matches->isEmpty() || ! $user->steam_id) {
            return [
                'opener' => 0,
                'closer' => 0,
                'support' => 0,
                'fragger' => 0,
            ];
        }

        // Calculate complexion scores for each match and average them
        $complexionScores = [
            'opener' => [],
            'closer' => [],
            'support' => [],
            'fragger' => [],
        ];

        foreach ($matches as $match) {
            try {
                $complexion = $this->playerComplexionService->get($user->steam_id, $match->match_id);

                if (! empty($complexion)) {
                    $complexionScores['opener'][] = $complexion['opener'] ?? 0;
                    $complexionScores['closer'][] = $complexion['closer'] ?? 0;
                    $complexionScores['support'][] = $complexion['support'] ?? 0;
                    $complexionScores['fragger'][] = $complexion['fragger'] ?? 0;
                }
            } catch (\Exception $e) {
                // Skip matches where complexion can't be calculated
                continue;
            }
        }

        return [
            'opener' => ! empty($complexionScores['opener']) ? round(array_sum($complexionScores['opener']) / count($complexionScores['opener']), 0) : 0,
            'closer' => ! empty($complexionScores['closer']) ? round(array_sum($complexionScores['closer']) / count($complexionScores['closer']), 0) : 0,
            'support' => ! empty($complexionScores['support']) ? round(array_sum($complexionScores['support']) / count($complexionScores['support']), 0) : 0,
            'fragger' => ! empty($complexionScores['fragger']) ? round(array_sum($complexionScores['fragger']) / count($complexionScores['fragger']), 0) : 0,
        ];
    }

    /**
     * Generate cache key
     */
    private function getCacheKey(string $tab, User $user, array $filters): string
    {
        $filterHash = md5(json_encode($filters));

        return "map-stats:{$tab}:{$user->steam_id}:{$filterHash}";
    }
}
