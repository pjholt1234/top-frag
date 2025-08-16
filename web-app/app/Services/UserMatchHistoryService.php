<?php

namespace App\Services;

use App\Models\GameMatch;
use App\Models\Player;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UserMatchHistoryService
{
    private ?Player $player;

    private ?User $user;

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

        // Start with base query
        $query = $this->player->matches();

        // Apply filters
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

        // Get paginated matches with eager loading
        $matches = $query
            ->with([
                'players',
                'gunfightEvents',
                'damageEvents',
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        $matchData = $matches->getCollection()->map(function (GameMatch $match) {
            return [
                'match_details' => $this->getMatchDetails($match),
                'player_stats' => $this->getPlayerStatsOptimized($match),
            ];
        })->toArray();

        return [
            'data' => $matchData,
            'pagination' => [
                'current_page' => $matches->currentPage(),
                'per_page' => $matches->perPage(),
                'total' => $matches->total(),
                'last_page' => $matches->lastPage(),
                'from' => $matches->firstItem(),
                'to' => $matches->lastItem(),
            ],
        ];
    }

    /**
     * Get recent match history with a limit for quick loading
     */
    public function getRecentMatchHistory(User $user, int $limit = 5): array
    {
        $this->setUser($user);

        if (! $this->player) {
            return [];
        }

        // Get recent matches with eager loading
        $matches = $this->player->matches()
            ->with([
                'players',
                'gunfightEvents',
                'damageEvents',
            ])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $matches->map(function (GameMatch $match) {
            return [
                'match_details' => $this->getMatchDetails($match),
                'player_stats' => $this->getPlayerStatsOptimized($match),
            ];
        })->toArray();
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
