<?php

namespace App\Services;

use App\Models\GameMatch;
use App\Models\Player;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class UserMatchHistoryService
{
    private const CACHE_TTL = 1800;

    private ?Player $player;

    private ?User $user;

    public function __construct(private readonly bool $cacheEnabled = true) {}

    public function setUser(User $user)
    {
        $this->user = $user;
        $this->player = $user->player;
    }

    public function aggregateMatchData(User $user): array
    {
        $this->setUser($user);
        if (! $this->player) {
            return [];
        }

        // Eager load all necessary relationships in a single query
        $matches = $this->player->matches()
            ->with([
                'players',
                'gunfightEvents',
                'damageEvents',
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        return $matches->map(function (GameMatch $match) {
            return [
                'match_details' => $this->getMatchDetails($match),
                'player_stats' => $this->getPlayerStatsOptimized($match),
            ];
        })->toArray();
    }

    /**
     * Get paginated match history for better performance with large datasets
     */
    public function getPaginatedMatchHistory(User $user, int $perPage = 10, int $page = 1, array $filters = []): array
    {
        $this->setUser($user);

        if (! $this->player) {
            return [
                'data' => [],
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => 0,
                    'last_page' => 1,
                ],
            ];
        }

        $completedMatches = $this->getCompletedMatches($filters);
        $inProgressJobs = $this->getInProgressJobs($user, $filters);

        $allMatches = collect([...$completedMatches, ...$inProgressJobs])
            ->sortByDesc('created_at')
            ->values();

        $total = $allMatches->count();
        $offset = ($page - 1) * $perPage;
        $paginatedMatches = $allMatches->slice($offset, $perPage);

        return [
            'data' => $paginatedMatches->toArray(),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $total),
            ],
        ];
    }

    /**
     * Get a single match by ID
     */
    public function getMatchById(User $user, int $matchId): ?array
    {
        $this->setUser($user);

        if (! $this->player) {
            return null;
        }

        // First check if it's a completed match
        $cachedMatch = $this->getCachedMatch($matchId);
        if ($cachedMatch !== null) {
            return $cachedMatch;
        }

        // Try to load and cache the match
        $match = $this->loadAndCacheMatch($matchId);
        if ($match) {
            return $match;
        }

        // Check if it's an in-progress job
        $inProgressJob = $this->getInProgressJobById($user, $matchId);
        if ($inProgressJob) {
            return $inProgressJob;
        }

        return null;
    }

    /**
     * Get completed matches with filters
     */
    private function getCompletedMatches(array $filters = []): array
    {
        $matchIds = $this->getFilteredMatchIds($filters);

        $completedMatches = [];

        foreach ($matchIds as $matchId) {
            $cachedMatch = $this->getCachedMatch($matchId);

            if ($cachedMatch !== null) {
                $completedMatches[] = $cachedMatch;
            } else {
                // Load and cache the match
                $match = $this->loadAndCacheMatch($matchId);
                if ($match) {
                    $completedMatches[] = $match;
                }
            }
        }

        return $completedMatches;
    }

    /**
     * Get filtered match IDs without loading full match data
     */
    private function getFilteredMatchIds(array $filters = []): array
    {
        $query = $this->player->matches()->select('matches.id');

        if (! empty($filters['map'])) {
            $query->where('map', 'like', '%'.$filters['map'].'%');
        }

        if (! empty($filters['match_type'])) {
            $query->where('match_type', $filters['match_type']);
        }

        if (isset($filters['player_was_participant']) && $filters['player_was_participant'] !== '') {
            $query->whereHas('players', function ($q) {
                $q->where('steam_id', $this->player->steam_id);
            });
        }

        if (isset($filters['player_won_match']) && $filters['player_won_match'] !== '') {
            $isWin = $filters['player_won_match'] === 'true';
            $query->where(function ($q) use ($isWin) {
                if ($isWin) {
                    $q->where('winning_team', 'A')->whereHas('players', function ($pq) {
                        $pq->where('steam_id', $this->player->steam_id)->where('team', 'A');
                    })->orWhere('winning_team', 'B')->whereHas('players', function ($pq) {
                        $pq->where('steam_id', $this->player->steam_id)->where('team', 'B');
                    });
                } else {
                    $q->where(function ($subQ) {
                        $subQ->where('winning_team', 'A')->whereHas('players', function ($pq) {
                            $pq->where('steam_id', $this->player->steam_id)->where('team', 'B');
                        })->orWhere('winning_team', 'B')->whereHas('players', function ($pq) {
                            $pq->where('steam_id', $this->player->steam_id)->where('team', 'A');
                        });
                    });
                }
            });
        }

        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to'].' 23:59:59');
        }

        return $query->orderBy('matches.created_at', 'desc')->pluck('id')->toArray();
    }

    /**
     * Get cached match data for a specific match
     */
    private function getCachedMatch(int $matchId): ?array
    {
        $cacheKey = $this->getMatchCacheKey($matchId);

        return Cache::get($cacheKey);
    }

    /**
     * Load and cache a single match
     */
    private function loadAndCacheMatch(int $matchId): ?array
    {
        $match = GameMatch::with(['players', 'gunfightEvents', 'damageEvents'])
            ->find($matchId);

        if (! $match) {
            return null;
        }

        $matchData = [
            'id' => $match->id,
            'created_at' => $match->created_at,
            'is_completed' => true,
            'match_details' => [
                'id' => $match->id,
                'map' => $match->map,
                'winning_team_score' => $match->winning_team_score,
                'losing_team_score' => $match->losing_team_score,
                'winning_team' => $match->winning_team,
                'match_type' => $match->match_type,
                'created_at' => $match->created_at,
            ],
            'player_stats' => $this->getPlayerStatsOptimized($match),
            'processing_status' => null,
            'progress_percentage' => null,
            'current_step' => null,
            'error_message' => null,
        ];

        if (! $this->cacheEnabled) {
            return $matchData;
        }

        $cacheKey = $this->getMatchCacheKey($matchId);
        Cache::put($cacheKey, $matchData, self::CACHE_TTL);

        return $matchData;
    }

    /**
     * Generate cache key for a specific match
     */
    private function getMatchCacheKey(int $matchId): string
    {
        return "match_data_{$matchId}";
    }

    /**
     * Get in-progress jobs with filters
     */
    private function getInProgressJobs(User $user, array $filters = []): array
    {
        $query = $user->demoProcessingJobs()
            ->where('progress_percentage', '<', 100)
            ->where('processing_status', '!=', \App\Enums\ProcessingStatus::COMPLETED->value)
            ->with('match');

        // Apply filters that work for in-progress jobs
        if (! empty($filters['map'])) {
            $query->whereHas('match', function ($q) use ($filters) {
                $q->where('map', 'like', '%'.$filters['map'].'%');
            });
        }

        if (! empty($filters['match_type'])) {
            $query->whereHas('match', function ($q) use ($filters) {
                $q->where('match_type', $filters['match_type']);
            });
        }

        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to'].' 23:59:59');
        }

        $jobs = $query->orderBy('created_at', 'desc')->get();

        return $jobs->map(function ($job) {
            $match = $job->match;

            $matchDetails = null;
            if ($match) {
                $matchDetails = [
                    'id' => $match->id,
                    'map' => $match->map,
                    'winning_team_score' => $match->winning_team_score,
                    'losing_team_score' => $match->losing_team_score,
                    'winning_team' => $match->winning_team,
                    'match_type' => $match->match_type,
                    'created_at' => $match->created_at,
                ];
            }

            return [
                'id' => $job->id,
                'created_at' => $job->created_at,
                'is_completed' => false,
                'match_details' => $matchDetails,
                'player_stats' => null, // Not available for in-progress jobs
                'processing_status' => $job->processing_status,
                'progress_percentage' => $job->progress_percentage,
                'current_step' => $job->current_step,
                'error_message' => $job->error_message,
            ];
        })->toArray();
    }

    /**
     * Get a single in-progress job by ID
     */
    private function getInProgressJobById(User $user, int $jobId): ?array
    {
        $job = $user->demoProcessingJobs()
            ->where('id', $jobId)
            ->where('progress_percentage', '<', 100)
            ->where('processing_status', '!=', \App\Enums\ProcessingStatus::COMPLETED->value)
            ->with('match')
            ->first();

        if (! $job) {
            return null;
        }

        $match = $job->match;

        $matchDetails = null;
        if ($match) {
            $matchDetails = [
                'id' => $match->id,
                'map' => $match->map,
                'winning_team_score' => $match->winning_team_score,
                'losing_team_score' => $match->losing_team_score,
                'winning_team' => $match->winning_team,
                'match_type' => $match->match_type,
                'created_at' => $match->created_at,
            ];
        }

        return [
            'id' => $job->id,
            'created_at' => $job->created_at,
            'is_completed' => false,
            'match_details' => $matchDetails,
            'player_stats' => null, // Not available for in-progress jobs
            'processing_status' => $job->processing_status,
            'progress_percentage' => $job->progress_percentage,
            'current_step' => $job->current_step,
            'error_message' => $job->error_message,
        ];
    }

    private function getMatchDetails(GameMatch $match): array
    {
        return [
            'match_id' => $match->id,
            'map' => $match->map,
            'winning_team_score' => $match->winning_team_score,
            'losing_team_score' => $match->losing_team_score,
            'winning_team_name' => $match->winning_team,
            'player_won_match' => $this->player->playerWonMatch($match),
            'match_type' => $match->match_type,
            'match_date' => $match->created_at,
            'player_was_participant' => true,
        ];
    }

    private function getPlayerStatsOptimized(GameMatch $match): array
    {
        // Pre-calculate all gunfight statistics for this match in a single query
        $gunfightStats = $this->getGunfightStatsForMatch($match);

        // Pre-calculate all damage statistics for this match in a single query
        $damageStats = $this->getDamageStatsForMatch($match);

        return $match->players->map(function (Player $player) use ($match, $gunfightStats, $damageStats) {
            $playerSteamId = $player->steam_id;
            $playerGunfightStats = $gunfightStats[$playerSteamId] ?? [
                'kills' => 0,
                'deaths' => 0,
                'first_kills' => 0,
                'first_deaths' => 0,
            ];

            $playerDamageStats = $damageStats[$playerSteamId] ?? ['total_damage' => 0];

            $openingKills = $playerGunfightStats['first_kills'] - $playerGunfightStats['first_deaths'];

            return [
                'player_kills' => $playerGunfightStats['kills'],
                'player_deaths' => $playerGunfightStats['deaths'],
                'player_first_kill_differential' => $openingKills,
                'player_kill_death_ratio' => $this->calculateKillDeathRatio(
                    $playerGunfightStats['kills'],
                    $playerGunfightStats['deaths']
                ),
                'player_adr' => round($playerDamageStats['total_damage'] / $match->total_rounds, 2),
                'team' => $match->players->where('steam_id', $playerSteamId)->first()->pivot->team,
                'player_name' => $player->name,
            ];
        })->toArray();
    }

    /**
     * Get gunfight statistics for all players in a match using a single optimized query
     */
    private function getGunfightStatsForMatch(GameMatch $match): array
    {
        $stats = DB::table('gunfight_events')
            ->select([
                'victor_steam_id',
                DB::raw('COUNT(*) as total_events'),
                DB::raw('SUM(CASE WHEN is_first_kill = 1 THEN 1 ELSE 0 END) as first_kills'),
            ])
            ->where('match_id', $match->id)
            ->groupBy('victor_steam_id')
            ->get()
            ->keyBy('victor_steam_id')
            ->toArray();

        // Get death statistics (events where player was involved but not the victor)
        $deathStats = DB::table('gunfight_events')
            ->select([
                'player_1_steam_id as steam_id',
                DB::raw('COUNT(*) as deaths'),
                DB::raw('SUM(CASE WHEN is_first_kill = 1 AND victor_steam_id != player_1_steam_id THEN 1 ELSE 0 END) as first_deaths'),
            ])
            ->where('match_id', $match->id)
            ->whereRaw('victor_steam_id != player_1_steam_id')
            ->groupBy('player_1_steam_id')
            ->union(
                DB::table('gunfight_events')
                    ->select([
                        'player_2_steam_id as steam_id',
                        DB::raw('COUNT(*) as deaths'),
                        DB::raw('SUM(CASE WHEN is_first_kill = 1 AND victor_steam_id != player_2_steam_id THEN 1 ELSE 0 END) as first_deaths'),
                    ])
                    ->where('match_id', $match->id)
                    ->whereRaw('victor_steam_id != player_2_steam_id')
                    ->groupBy('player_2_steam_id')
            )
            ->get()
            ->groupBy('steam_id')
            ->map(function ($group) {
                return [
                    'deaths' => $group->sum('deaths'),
                    'first_deaths' => $group->sum('first_deaths'),
                ];
            })
            ->toArray();

        // Combine kill and death statistics
        $result = [];
        foreach ($stats as $steamId => $killStats) {
            $result[$steamId] = [
                'kills' => $killStats->total_events,
                'deaths' => $deathStats[$steamId]['deaths'] ?? 0,
                'first_kills' => $killStats->first_kills,
                'first_deaths' => $deathStats[$steamId]['first_deaths'] ?? 0,
            ];
        }

        // Add players who only died (no kills)
        foreach ($deathStats as $steamId => $deathData) {
            if (! isset($result[$steamId])) {
                $result[$steamId] = [
                    'kills' => 0,
                    'deaths' => $deathData['deaths'],
                    'first_kills' => 0,
                    'first_deaths' => $deathData['first_deaths'],
                ];
            }
        }

        return $result;
    }

    /**
     * Get damage statistics for all players in a match using a single optimized query
     */
    private function getDamageStatsForMatch(GameMatch $match): array
    {
        return DB::table('damage_events')
            ->select([
                'attacker_steam_id',
                DB::raw('SUM(health_damage) as total_damage'),
            ])
            ->where('match_id', $match->id)
            ->groupBy('attacker_steam_id')
            ->get()
            ->keyBy('attacker_steam_id')
            ->map(function ($item) {
                return ['total_damage' => $item->total_damage];
            })
            ->toArray();
    }

    /**
     * Legacy method for backward compatibility - now uses optimized approach
     */
    public function getAllPlayerGunfightEvents(GameMatch $match, Player $player): Collection
    {
        return $match
            ->gunfightEvents()
            ->where(function ($query) use ($player) {
                $query->where('player_1_steam_id', $player->steam_id)
                    ->orWhere('player_2_steam_id', $player->steam_id);
            })
            ->get();
    }

    private function calculateKillDeathRatio(int $kills, int $deaths): float
    {
        if ($deaths === 0) {
            return 0.0;
        }

        return round($kills / $deaths, 2);
    }

    /**
     * Legacy method for backward compatibility - now uses optimized approach
     */
    private function calculatePlayerAverageDamagePerRound(GameMatch $match, Player $player)
    {
        $totalDamage = $match
            ->damageEvents()
            ->where('attacker_steam_id', $player->steam_id)
            ->sum('health_damage');

        return round($totalDamage / $match->total_rounds, 2);
    }
}
