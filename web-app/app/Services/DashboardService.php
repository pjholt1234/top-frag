<?php

namespace App\Services;

use App\Enums\AchievementType;
use App\Models\Achievement;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\PlayerMatchAimEvent;
use App\Models\PlayerMatchEvent;
use App\Models\PlayerRank;
use App\Models\User;
use App\Services\Matches\PlayerComplexionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class DashboardService
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
     * Get player stats dashboard data with filters and trends
     */
    public function getPlayerStats(User $user, array $filters): array
    {
        $cacheKey = $this->getCacheKey('player-stats', $user, $filters);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user, $filters) {
            return $this->buildPlayerStats($user, $filters);
        });
    }

    /**
     * Get aim stats dashboard data with filters and trends
     */
    public function getAimStats(User $user, array $filters): array
    {
        $cacheKey = $this->getCacheKey('aim', $user, $filters);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user, $filters) {
            return $this->buildAimStats($user, $filters);
        });
    }

    /**
     * Get utility stats dashboard data with filters and trends
     */
    public function getUtilityStats(User $user, array $filters): array
    {
        $cacheKey = $this->getCacheKey('utility', $user, $filters);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user, $filters) {
            return $this->buildUtilityStats($user, $filters);
        });
    }

    /**
     * Get summary dashboard data with filters and trends
     */
    public function getSummary(User $user, array $filters): array
    {
        $cacheKey = $this->getCacheKey('summary', $user, $filters);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user, $filters) {
            return $this->buildSummary($user, $filters);
        });
    }

    /**
     * Get map stats dashboard data with filters
     */
    public function getMapStats(User $user, array $filters): array
    {
        $cacheKey = $this->getCacheKey('map-stats', $user, $filters);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user, $filters) {
            return $this->buildMapStats($user, $filters);
        });
    }

    /**
     * Get rank stats dashboard data with filters
     */
    public function getRankStats(User $user, array $filters): array
    {
        $cacheKey = $this->getCacheKey('rank-stats', $user, $filters);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user, $filters) {
            return $this->buildRankStats($user, $filters);
        });
    }

    /**
     * Invalidate all dashboard caches for a user
     */
    public function invalidateUserCache(string $steamId): void
    {
        $tabs = ['player-stats', 'aim', 'utility', 'summary', 'map-stats', 'rank-stats'];

        foreach ($tabs as $tab) {
            // We invalidate by pattern since we don't know all filter combinations
            // This requires using cache tags or a more sophisticated approach
            Cache::forget("dashboard:{$tab}:{$steamId}:*");
        }
    }

    /**
     * Warm dashboard cache for all players in a match
     */
    public function warmCacheForMatch(int $matchId): void
    {
        try {
            $match = GameMatch::find($matchId);

            if (! $match) {
                return;
            }

            // Get all users who participated in this match
            $playerSteamIds = $match->players->pluck('steam_id')->unique();

            foreach ($playerSteamIds as $steamId) {
                $user = User::where('steam_id', $steamId)->first();

                if (! $user) {
                    continue;
                }

                // Warm cache for common filter combinations
                $matchCounts = [5, 10, 15, 30];

                foreach ($matchCounts as $count) {
                    $filters = ['past_match_count' => $count];

                    try {
                        // Warm all dashboard tabs
                        $this->getPlayerStats($user, $filters);
                        $this->getAimStats($user, $filters);
                        $this->getUtilityStats($user, $filters);
                        $this->getSummary($user, $filters);
                        $this->getMapStats($user, $filters);
                        $this->getRankStats($user, $filters);
                    } catch (\Exception $e) {
                        // Silently fail individual cache warming attempts
                        continue;
                    }
                }
            }
        } catch (\Exception $e) {
            // Don't let cache warming errors break match processing
            return;
        }
    }

    /**
     * Build player stats section
     */
    private function buildPlayerStats(User $user, array $filters): array
    {
        $currentMatches = $this->getMatchesForPeriod($user, $filters);
        $previousMatches = $this->getMatchesForPeriod($user, $filters, true);

        $currentStats = $this->aggregatePlayerMatchStats($currentMatches);
        $previousStats = $this->aggregatePlayerMatchStats($previousMatches);

        return [
            'opening_stats' => [
                'total_opening_kills' => $this->buildStatWithTrend(
                    $currentStats['total_opening_kills'],
                    $previousStats['total_opening_kills']
                ),
                'total_opening_deaths' => $this->buildStatWithTrend(
                    $currentStats['total_opening_deaths'],
                    $previousStats['total_opening_deaths']
                ),
                'opening_duel_winrate' => $this->buildStatWithTrend(
                    $currentStats['opening_duel_winrate'],
                    $previousStats['opening_duel_winrate']
                ),
                'average_opening_kills' => $this->buildStatWithTrend(
                    $currentStats['average_opening_kills'],
                    $previousStats['average_opening_kills']
                ),
                'average_opening_deaths' => $this->buildStatWithTrend(
                    $currentStats['average_opening_deaths'],
                    $previousStats['average_opening_deaths'],
                    true
                ),
                'average_duel_winrate' => $this->buildStatWithTrend(
                    $currentStats['average_duel_winrate'],
                    $previousStats['average_duel_winrate']
                ),
            ],
            'trading_stats' => [
                'total_trades' => $this->buildStatWithTrend(
                    $currentStats['total_trades'],
                    $previousStats['total_trades']
                ),
                'total_possible_trades' => $this->buildStatWithTrend(
                    $currentStats['total_possible_trades'],
                    $previousStats['total_possible_trades']
                ),
                'total_traded_deaths' => $this->buildStatWithTrend(
                    $currentStats['total_traded_deaths'],
                    $previousStats['total_traded_deaths']
                ),
                'total_possible_traded_deaths' => $this->buildStatWithTrend(
                    $currentStats['total_possible_traded_deaths'],
                    $previousStats['total_possible_traded_deaths']
                ),
                'average_trades' => $this->buildStatWithTrend(
                    $currentStats['average_trades'],
                    $previousStats['average_trades']
                ),
                'average_possible_trades' => $this->buildStatWithTrend(
                    $currentStats['average_possible_trades'],
                    $previousStats['average_possible_trades']
                ),
                'average_traded_deaths' => $this->buildStatWithTrend(
                    $currentStats['average_traded_deaths'],
                    $previousStats['average_traded_deaths']
                ),
                'average_possible_traded_deaths' => $this->buildStatWithTrend(
                    $currentStats['average_possible_traded_deaths'],
                    $previousStats['average_possible_traded_deaths']
                ),
                'average_trade_success_rate' => $this->buildStatWithTrend(
                    $currentStats['average_trade_success_rate'],
                    $previousStats['average_trade_success_rate']
                ),
                'average_traded_death_success_rate' => $this->buildStatWithTrend(
                    $currentStats['average_traded_death_success_rate'],
                    $previousStats['average_traded_death_success_rate']
                ),
            ],
            'clutch_stats' => $currentStats['clutch_stats'],
        ];
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
     * Build summary section
     */
    private function buildSummary(User $user, array $filters): array
    {
        $currentMatches = $this->getMatchesForPeriod($user, $filters);
        $previousMatches = $this->getMatchesForPeriod($user, $filters, true);

        // Get all stats with trends
        $playerStats = $this->aggregatePlayerMatchStats($currentMatches);
        $previousPlayerStats = $this->aggregatePlayerMatchStats($previousMatches);

        $aimStats = $this->aggregateAimStats($currentMatches);
        $previousAimStats = $this->aggregateAimStats($previousMatches);

        $utilityStats = $this->aggregateUtilityStats($currentMatches);
        $previousUtilityStats = $this->aggregateUtilityStats($previousMatches);

        // Build all stats with trends for comparison
        $allStatsWithTrends = [
            'Win Rate' => $this->buildStatWithTrend(
                $playerStats['win_percentage'],
                $previousPlayerStats['win_percentage']
            ),
            'K/D Ratio' => $this->buildStatWithTrend(
                $playerStats['average_kd'],
                $previousPlayerStats['average_kd']
            ),
            'Average Kills' => $this->buildStatWithTrend(
                $playerStats['average_kills'],
                $previousPlayerStats['average_kills']
            ),
            'Aim Rating' => $this->buildStatWithTrend(
                $aimStats['aim_rating'],
                $previousAimStats['aim_rating']
            ),
            'Headshot %' => $this->buildStatWithTrend(
                $aimStats['headshot_percentage'],
                $previousAimStats['headshot_percentage']
            ),
            'Crosshair Placement' => $this->buildStatWithTrend(
                $aimStats['crosshair_placement'],
                $previousAimStats['crosshair_placement'],
                true
            ),
            'Grenade Effectiveness' => $this->buildStatWithTrend(
                $utilityStats['grenade_effectiveness'],
                $previousUtilityStats['grenade_effectiveness']
            ),
            'Enemy Flash Duration' => $this->buildStatWithTrend(
                $utilityStats['enemy_flash_duration'],
                $previousUtilityStats['enemy_flash_duration']
            ),
        ];

        // Separate stats by trend direction
        $statsCollection = collect($allStatsWithTrends);

        $improvedStats = $statsCollection
            ->filter(fn ($stat) => $stat['trend'] === 'up' && $stat['change'] > 0)
            ->sortByDesc('change')
            ->take(2)
            ->map(fn ($stat, $name) => array_merge($stat, ['name' => $name]))
            ->values()
            ->toArray();

        $declinedStats = $statsCollection
            ->filter(fn ($stat) => $stat['trend'] === 'down' && $stat['change'] > 0)
            ->sortByDesc('change')
            ->take(2)
            ->map(fn ($stat, $name) => array_merge($stat, ['name' => $name]))
            ->values()
            ->toArray();

        $mostImproved = ! empty($improvedStats) ? $improvedStats : null;
        $leastImproved = ! empty($declinedStats) ? $declinedStats : null;

        // Get player complexion data
        $complexion = $this->getPlayerComplexion($user, $currentMatches);

        // Get achievement counts
        $achievementCounts = $this->getAchievementCounts($user, $filters);

        return [
            'most_improved_stats' => $mostImproved,
            'least_improved_stats' => $leastImproved,
            'average_aim_rating' => [
                'value' => $aimStats['aim_rating'],
                'max' => 100,
            ],
            'average_utility_effectiveness' => [
                'value' => $utilityStats['grenade_effectiveness'],
                'max' => 100,
            ],
            'achievements' => $achievementCounts,
            'player_card' => [
                'username' => $user->steam_persona_name ?? $user->name,
                'avatar' => $user->steam_avatar_full ?? $user->steam_avatar_medium ?? $user->steam_avatar,
                'average_impact' => $currentMatches->avg('average_impact') ?? 0,
                'average_round_swing' => $currentMatches->avg('match_swing_percent') ?? 0,
                'average_kd' => $playerStats['average_kd'],
                'average_adr' => $playerStats['average_adr'],
                'average_kills' => $playerStats['average_kills'],
                'average_deaths' => $playerStats['average_deaths'],
                'total_kills' => $playerStats['total_kills'],
                'total_deaths' => $playerStats['total_deaths'],
                'total_matches' => $playerStats['total_matches'],
                'win_percentage' => $playerStats['win_percentage'],
                'player_complexion' => $complexion,
            ],
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

        $query = PlayerMatchEvent::query()
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
     * Aggregate player match stats from a collection of matches
     */
    private function aggregatePlayerMatchStats(Collection $matches): array
    {
        if ($matches->isEmpty()) {
            return $this->getEmptyPlayerMatchStats();
        }

        // Preload win status to avoid N+1 queries
        $this->preloadWinStatus($matches);

        $totalMatches = $matches->count();

        // Basic stats
        $totalKills = $matches->sum('kills');
        $totalDeaths = $matches->sum('deaths');
        $totalAdr = $matches->sum('adr');

        // High level stats
        $averageImpact = round($matches->avg('average_impact'), 2);
        $averageRoundSwing = round($matches->avg('match_swing_percent'), 1);

        // Opening stats
        $totalOpeningKills = $matches->sum('first_kills');
        $totalOpeningDeaths = $matches->sum('first_deaths');
        $totalOpeningDuels = $totalOpeningKills + $totalOpeningDeaths;
        $openingDuelWinrate = $totalOpeningDuels > 0
            ? round(($totalOpeningKills / $totalOpeningDuels) * 100, 1)
            : 0;

        $averageOpeningKills = $totalMatches > 0 ? round($totalOpeningKills / $totalMatches, 1) : 0;
        $averageOpeningDeaths = $totalMatches > 0 ? round($totalOpeningDeaths / $totalMatches, 1) : 0;

        // Calculate average duel winrate across matches
        $duelWinrates = $matches->map(function ($match) {
            $matchDuels = $match->first_kills + $match->first_deaths;

            return $matchDuels > 0 ? ($match->first_kills / $matchDuels) * 100 : 0;
        });
        $averageDuelWinrate = round($duelWinrates->avg(), 1);

        // Trading stats
        $totalTrades = $matches->sum('total_successful_trades');
        $totalTradedDeaths = $matches->sum('total_traded_deaths');
        $totalPossibleTrades = $matches->sum('total_possible_trades');
        $totalPossibleTradedDeaths = $matches->sum('total_possible_traded_deaths');

        $tradeSuccessRate = $totalPossibleTrades > 0
            ? round(($totalTrades / $totalPossibleTrades) * 100, 1)
            : 0;
        $tradedDeathSuccessRate = $totalPossibleTradedDeaths > 0
            ? round(($totalTradedDeaths / $totalPossibleTradedDeaths) * 100, 1)
            : 0;

        // Clutch stats
        $clutchStats = [
            '1v1' => [
                'total' => $matches->sum('clutch_wins_1v1'),
                'attempts' => $matches->sum('clutch_attempts_1v1'),
                'winrate' => $matches->sum('clutch_attempts_1v1') > 0
                    ? round(($matches->sum('clutch_wins_1v1') / $matches->sum('clutch_attempts_1v1')) * 100, 1)
                    : 0,
            ],
            '1v2' => [
                'total' => $matches->sum('clutch_wins_1v2'),
                'attempts' => $matches->sum('clutch_attempts_1v2'),
                'winrate' => $matches->sum('clutch_attempts_1v2') > 0
                    ? round(($matches->sum('clutch_wins_1v2') / $matches->sum('clutch_attempts_1v2')) * 100, 1)
                    : 0,
            ],
            '1v3' => [
                'total' => $matches->sum('clutch_wins_1v3'),
                'attempts' => $matches->sum('clutch_attempts_1v3'),
                'winrate' => $matches->sum('clutch_attempts_1v3') > 0
                    ? round(($matches->sum('clutch_wins_1v3') / $matches->sum('clutch_attempts_1v3')) * 100, 1)
                    : 0,
            ],
            '1v4' => [
                'total' => $matches->sum('clutch_wins_1v4'),
                'attempts' => $matches->sum('clutch_attempts_1v4'),
                'winrate' => $matches->sum('clutch_attempts_1v4') > 0
                    ? round(($matches->sum('clutch_wins_1v4') / $matches->sum('clutch_attempts_1v4')) * 100, 1)
                    : 0,
            ],
            '1v5' => [
                'total' => $matches->sum('clutch_wins_1v5'),
                'attempts' => $matches->sum('clutch_attempts_1v5'),
                'winrate' => $matches->sum('clutch_attempts_1v5') > 0
                    ? round(($matches->sum('clutch_wins_1v5') / $matches->sum('clutch_attempts_1v5')) * 100, 1)
                    : 0,
            ],
        ];

        $totalClutchWins = collect($clutchStats)->sum('total');
        $totalClutchAttempts = collect($clutchStats)->sum('attempts');
        $clutchStats['overall'] = [
            'total' => $totalClutchWins,
            'attempts' => $totalClutchAttempts,
            'winrate' => $totalClutchAttempts > 0
                ? round(($totalClutchWins / $totalClutchAttempts) * 100, 1)
                : 0,
        ];

        // Calculate win percentage
        $wins = $matches->filter(function ($match) {
            return $this->didPlayerWinMatch($match->match_id, $match->player_steam_id);
        })->count();
        $winPercentage = $totalMatches > 0 ? round(($wins / $totalMatches) * 100, 1) : 0;

        return [
            'total_matches' => $totalMatches,
            'win_percentage' => $winPercentage,
            'total_kills' => $totalKills,
            'total_deaths' => $totalDeaths,
            'average_kills' => $totalMatches > 0 ? round($totalKills / $totalMatches, 1) : 0,
            'average_deaths' => $totalMatches > 0 ? round($totalDeaths / $totalMatches, 1) : 0,
            'average_kd' => $totalDeaths > 0 ? round($totalKills / $totalDeaths, 2) : 0,
            'average_adr' => $totalMatches > 0 ? round($totalAdr / $totalMatches, 1) : 0,
            'average_impact' => $averageImpact,
            'average_round_swing' => $averageRoundSwing,
            'total_opening_kills' => $totalOpeningKills,
            'total_opening_deaths' => $totalOpeningDeaths,
            'opening_duel_winrate' => $openingDuelWinrate,
            'average_opening_kills' => $averageOpeningKills,
            'average_opening_deaths' => $averageOpeningDeaths,
            'average_duel_winrate' => $averageDuelWinrate,
            'total_trades' => $totalTrades,
            'total_possible_trades' => $totalPossibleTrades,
            'total_traded_deaths' => $totalTradedDeaths,
            'total_possible_traded_deaths' => $totalPossibleTradedDeaths,
            'average_trades' => $totalMatches > 0 ? round($totalTrades / $totalMatches, 1) : 0,
            'average_possible_trades' => $totalMatches > 0 ? round($totalPossibleTrades / $totalMatches, 1) : 0,
            'average_traded_deaths' => $totalMatches > 0 ? round($totalTradedDeaths / $totalMatches, 1) : 0,
            'average_possible_traded_deaths' => $totalMatches > 0 ? round($totalPossibleTradedDeaths / $totalMatches, 1) : 0,
            'average_trade_success_rate' => $tradeSuccessRate,
            'average_traded_death_success_rate' => $tradedDeathSuccessRate,
            'clutch_stats' => $clutchStats,
        ];
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
     * Generate cache key for dashboard data
     */
    private function getCacheKey(string $tab, User $user, array $filters): string
    {
        $filterHash = md5(json_encode($filters));

        return "dashboard:{$tab}:{$user->steam_id}:{$filterHash}";
    }

    /**
     * Get empty player match stats structure
     */
    private function getEmptyPlayerMatchStats(): array
    {
        return [
            'total_matches' => 0,
            'win_percentage' => 0,
            'total_kills' => 0,
            'total_deaths' => 0,
            'average_kills' => 0,
            'average_deaths' => 0,
            'average_kd' => 0,
            'average_adr' => 0,
            'average_impact' => 0,
            'average_round_swing' => 0,
            'total_opening_kills' => 0,
            'total_opening_deaths' => 0,
            'opening_duel_winrate' => 0,
            'average_opening_kills' => 0,
            'average_opening_deaths' => 0,
            'average_duel_winrate' => 0,
            'total_trades' => 0,
            'total_possible_trades' => 0,
            'total_traded_deaths' => 0,
            'total_possible_traded_deaths' => 0,
            'average_trades' => 0,
            'average_possible_trades' => 0,
            'average_traded_deaths' => 0,
            'average_possible_traded_deaths' => 0,
            'average_trade_success_rate' => 0,
            'average_traded_death_success_rate' => 0,
            'clutch_stats' => [
                '1v1' => ['total' => 0, 'attempts' => 0, 'winrate' => 0],
                '1v2' => ['total' => 0, 'attempts' => 0, 'winrate' => 0],
                '1v3' => ['total' => 0, 'attempts' => 0, 'winrate' => 0],
                '1v4' => ['total' => 0, 'attempts' => 0, 'winrate' => 0],
                '1v5' => ['total' => 0, 'attempts' => 0, 'winrate' => 0],
                'overall' => ['total' => 0, 'attempts' => 0, 'winrate' => 0],
            ],
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
     * Get achievement counts for a user within filtered matches
     */
    private function getAchievementCounts(User $user, array $filters): array
    {
        if (! $user->steam_id) {
            return $this->getEmptyAchievementCounts();
        }

        // Get player
        $player = Player::where('steam_id', $user->steam_id)->first();
        if (! $player) {
            return $this->getEmptyAchievementCounts();
        }

        // Build query for matches based on filters
        $query = Achievement::query()
            ->join('matches', 'achievements.match_id', '=', 'matches.id')
            ->where('achievements.player_id', $player->id);

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

        // Count achievements by type
        $counts = $query
            ->selectRaw('award_name, COUNT(*) as count')
            ->groupBy('award_name')
            ->pluck('count', 'award_name')
            ->toArray();

        return [
            'fragger' => $counts[AchievementType::FRAGGER->value] ?? 0,
            'support' => $counts[AchievementType::SUPPORT->value] ?? 0,
            'opener' => $counts[AchievementType::OPENER->value] ?? 0,
            'closer' => $counts[AchievementType::CLOSER->value] ?? 0,
            'top_aimer' => $counts[AchievementType::TOP_AIMER->value] ?? 0,
            'impact_player' => $counts[AchievementType::IMPACT_PLAYER->value] ?? 0,
            'difference_maker' => $counts[AchievementType::DIFFERENCE_MAKER->value] ?? 0,
        ];
    }

    /**
     * Get empty achievement counts
     */
    private function getEmptyAchievementCounts(): array
    {
        return [
            'fragger' => 0,
            'support' => 0,
            'opener' => 0,
            'closer' => 0,
            'top_aimer' => 0,
            'impact_player' => 0,
            'difference_maker' => 0,
        ];
    }
}
