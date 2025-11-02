<?php

namespace App\Services;

use App\Enums\AchievementType;
use App\Models\Achievement;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\PlayerMatchAimEvent;
use App\Models\PlayerMatchEvent;
use App\Models\User;
use App\Services\Matches\PlayerComplexionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class PlayerCardService
{
    private const CACHE_TTL = 900; // 15 minutes

    private const MATCH_COUNT = 20;

    /**
     * Cache for win status to avoid N+1 queries
     */
    private array $winStatusCache = [];

    public function __construct(
        private readonly PlayerComplexionService $playerComplexionService
    ) {}

    /**
     * Get player card data for a given steam ID
     */
    public function getPlayerCard(string $steamId): array
    {
        $cacheKey = $this->getCacheKey($steamId);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($steamId) {
            return $this->buildPlayerCard($steamId);
        });
    }

    /**
     * Invalidate cache for a specific player
     */
    public function invalidatePlayerCache(string $steamId): void
    {
        $cacheKey = $this->getCacheKey($steamId);
        Cache::forget($cacheKey);
    }

    /**
     * Build player card data
     */
    private function buildPlayerCard(string $steamId): array
    {
        // Get player
        $player = Player::where('steam_id', $steamId)->first();
        if (! $player) {
            return $this->getEmptyPlayerCard();
        }

        // Get user if exists (for avatar/username)
        $user = User::where('steam_id', $steamId)->first();

        // Get last 20 matches
        $matches = $this->getMatchesForPlayer($steamId);

        if ($matches->isEmpty()) {
            return $this->getEmptyPlayerCard();
        }

        // Preload win status to avoid N+1 queries
        $this->preloadWinStatus($matches);

        // Get all stats
        $playerStats = $this->aggregatePlayerMatchStats($matches);
        $aimStats = $this->aggregateAimStats($matches);
        $utilityStats = $this->aggregateUtilityStats($matches);

        // Get player complexion data
        $complexion = $this->getPlayerComplexion($matches, $steamId);

        // Get achievement counts
        $achievementCounts = $this->getAchievementCounts($player->id);

        return [
            'player_card' => [
                'username' => $user?->steam_persona_name ?? $user?->name ?? $player->name,
                'avatar' => $user?->steam_avatar_full ?? $user?->steam_avatar_medium ?? $user?->steam_avatar ?? null,
                'average_impact' => $matches->avg('average_impact') ?? 0,
                'average_round_swing' => $matches->avg('match_swing_percent') ?? 0,
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
            'achievements' => $achievementCounts,
        ];
    }

    /**
     * Get matches for a player (last 20)
     */
    private function getMatchesForPlayer(string $steamId): Collection
    {
        return PlayerMatchEvent::query()
            ->select(
                'player_match_events.*',
                'matches.id as match_id',
                'matches.map',
                'matches.match_type',
                'matches.created_at'
            )
            ->join('matches', 'player_match_events.match_id', '=', 'matches.id')
            ->where('player_match_events.player_steam_id', $steamId)
            ->orderBy('matches.created_at', 'desc')
            ->take(self::MATCH_COUNT)
            ->get();
    }

    /**
     * Aggregate player match stats from a collection of matches
     */
    private function aggregatePlayerMatchStats(Collection $matches): array
    {
        if ($matches->isEmpty()) {
            return $this->getEmptyPlayerMatchStats();
        }

        $totalMatches = $matches->count();

        // Basic stats
        $totalKills = $matches->sum('kills');
        $totalDeaths = $matches->sum('deaths');
        $totalAdr = $matches->sum('adr');

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
        ];
    }

    /**
     * Aggregate aim stats from matches
     */
    private function aggregateAimStats(Collection $matches): array
    {
        if ($matches->isEmpty()) {
            return [];
        }

        $matchIds = $matches->pluck('match_id')->unique();
        $steamId = $matches->first()->player_steam_id;

        $aimEvents = PlayerMatchAimEvent::query()
            ->whereIn('match_id', $matchIds)
            ->where('player_steam_id', $steamId)
            ->get();

        if ($aimEvents->isEmpty()) {
            return [];
        }

        return [];
    }

    /**
     * Aggregate utility stats from matches
     */
    private function aggregateUtilityStats(Collection $matches): array
    {
        if ($matches->isEmpty()) {
            return [];
        }

        return [];
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
    private function getPlayerComplexion(Collection $matches, string $steamId): array
    {
        if ($matches->isEmpty()) {
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
                $complexion = $this->playerComplexionService->get($steamId, $match->match_id);

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
     * Get achievement counts for a player
     */
    private function getAchievementCounts(int $playerId): array
    {
        // Count achievements by type for this player
        $counts = Achievement::query()
            ->where('player_id', $playerId)
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
     * Generate cache key for player card data
     */
    private function getCacheKey(string $steamId): string
    {
        return "player-card:{$steamId}";
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
        ];
    }

    /**
     * Get empty player card structure
     */
    private function getEmptyPlayerCard(): array
    {
        return [
            'player_card' => [
                'username' => 'Unknown',
                'avatar' => null,
                'average_impact' => 0,
                'average_round_swing' => 0,
                'average_kd' => 0,
                'average_adr' => 0,
                'average_kills' => 0,
                'average_deaths' => 0,
                'total_kills' => 0,
                'total_deaths' => 0,
                'total_matches' => 0,
                'win_percentage' => 0,
                'player_complexion' => [
                    'opener' => 0,
                    'closer' => 0,
                    'support' => 0,
                    'fragger' => 0,
                ],
            ],
            'achievements' => [
                'fragger' => 0,
                'support' => 0,
                'opener' => 0,
                'closer' => 0,
                'top_aimer' => 0,
                'impact_player' => 0,
                'difference_maker' => 0,
            ],
        ];
    }
}
