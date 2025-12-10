<?php

namespace App\Services\MatchTimeExtraction;

use App\Models\Player;
use App\Models\PlayerRank;
use App\Services\FaceITRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class FaceItMatchStrategy implements MatchTimeStrategy
{
    public function __construct(
        private readonly FaceITRepository $faceITRepository
    ) {}

    /**
     * Extract match start time from FACEIT demo file name.
     *
     * Pattern: 1-{uuid}-1-1.dem
     * Example: 1-25e72cdb-ac23-4237-a95d-701603b58681-1-1.dem
     *
     * @param  string|null  $originalFileName  The original file name of the demo
     * @param  \App\Models\GameMatch  $gameMatch  The game match to validate against
     * @return Carbon|null The match start time, or null if extraction fails
     */
    public function extract(?string $originalFileName, \App\Models\GameMatch $gameMatch): ?Carbon
    {
        if (empty($originalFileName)) {
            return null;
        }

        // Match pattern: 1-{uuid}-1-1.dem
        $pattern = '/^1-([a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12})-1-1\.dem$/i';

        if (! preg_match($pattern, $originalFileName, $matches)) {
            return null;
        }

        // Extract match ID: 1-{uuid}
        $matchId = '1-'.$matches[1];

        try {
            // Query match details
            $matchDetails = $this->faceITRepository->getMatchDetails($matchId);

            // Query match statistics
            $matchStats = $this->faceITRepository->getMatchStats($matchId);

            // Validate match data against GameMatch
            if (! $this->validateMatch($matchStats, $gameMatch)) {
                Log::channel('parser')->warning('FACEIT match validation failed', [
                    'match_id' => $matchId,
                    'game_match_id' => $gameMatch->id,
                ]);

                return null;
            }

            // Process players and update ranks
            $this->processPlayers($matchDetails, $gameMatch);

            if (isset($matchDetails['started_at']) && is_numeric($matchDetails['started_at'])) {
                return Carbon::createFromTimestamp($matchDetails['started_at']);
            }

            return null;
        } catch (\Exception $e) {
            Log::channel('parser')->warning('Failed to retrieve FACEIT match data', [
                'match_id' => $matchId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Validate that the FACEIT match matches the GameMatch.
     *
     * @param  array  $matchStats  The match stats from FACEIT API
     * @param  \App\Models\GameMatch  $gameMatch  The game match to validate against
     * @return bool True if validation passes, false otherwise
     */
    private function validateMatch(array $matchStats, \App\Models\GameMatch $gameMatch): bool
    {
        $roundStats = $this->extractRoundStats($matchStats);

        if (! $roundStats) {
            return false;
        }

        return $this->validateMap($roundStats, $gameMatch)
            && $this->validateScore($roundStats, $gameMatch);
    }

    /**
     * Extract round stats from match stats.
     *
     * @param  array  $matchStats  The match stats from FACEIT API
     * @return array|null Round stats array or null if not found
     */
    private function extractRoundStats(array $matchStats): ?array
    {
        if (! isset($matchStats['rounds']) || ! is_array($matchStats['rounds']) || empty($matchStats['rounds'])) {
            return null;
        }

        $firstRound = $matchStats['rounds'][0];

        return $firstRound['round_stats'] ?? null;
    }

    /**
     * Validate that the map matches.
     *
     * @param  array  $roundStats  The round stats from FACEIT
     * @param  \App\Models\GameMatch  $gameMatch  The game match
     * @return bool True if map matches
     */
    private function validateMap(array $roundStats, \App\Models\GameMatch $gameMatch): bool
    {
        $faceitMap = $roundStats['Map'] ?? null;

        return $faceitMap !== null && $faceitMap === $gameMatch->map;
    }

    /**
     * Validate that the score matches.
     *
     * @param  array  $roundStats  The round stats from FACEIT
     * @param  \App\Models\GameMatch  $gameMatch  The game match
     * @return bool True if score matches
     */
    private function validateScore(array $roundStats, \App\Models\GameMatch $gameMatch): bool
    {
        $faceitScore = $roundStats['Score'] ?? null;

        if ($faceitScore === null) {
            return false;
        }

        $expectedScore = "{$gameMatch->winning_team_score} / {$gameMatch->losing_team_score}";

        return $faceitScore === $expectedScore;
    }

    /**
     * Process players from FACEIT match details and update ranks.
     *
     * @param  array  $matchDetails  The match details from FACEIT API
     * @param  \App\Models\GameMatch  $gameMatch  The game match
     */
    private function processPlayers(array $matchDetails, \App\Models\GameMatch $gameMatch): void
    {
        if (! isset($matchDetails['teams']) || ! is_array($matchDetails['teams'])) {
            return;
        }

        $faceitPlayerData = $this->collectFaceitPlayerData($matchDetails['teams']);

        if (empty($faceitPlayerData)) {
            return;
        }

        $matchPlayers = $gameMatch->matchPlayers()->with('player')->get();

        if (! $this->validatePlayerCounts($faceitPlayerData, $matchPlayers, $gameMatch)) {
            return;
        }

        $this->updatePlayersAndRanks($matchPlayers, $faceitPlayerData, $gameMatch);
    }

    /**
     * Collect player data from FACEIT teams.
     *
     * @param  array  $teams  The teams array from FACEIT match details
     * @return array<string, array> Array keyed by steam_id with player data
     */
    private function collectFaceitPlayerData(array $teams): array
    {
        $faceitPlayerData = [];

        foreach ($teams as $faction) {
            if (! isset($faction['roster']) || ! is_array($faction['roster'])) {
                continue;
            }

            foreach ($faction['roster'] as $player) {
                $playerData = $this->fetchPlayerData($player['player_id'] ?? null);

                if ($playerData) {
                    $faceitPlayerData[$playerData['steam_id']] = $playerData;
                }
            }
        }

        return $faceitPlayerData;
    }

    /**
     * Fetch player data from FACEIT API.
     *
     * @param  string|null  $faceitId  The FACEIT player ID
     * @return array|null Player data array or null if fetch fails
     */
    private function fetchPlayerData(?string $faceitId): ?array
    {
        if (empty($faceitId)) {
            return null;
        }

        try {
            $playerDetails = $this->faceITRepository->getPlayerByFaceITID($faceitId);

            $steamId = $playerDetails['games']['cs2']['game_player_id'] ?? null;
            $elo = $playerDetails['games']['cs2']['faceit_elo'] ?? null;
            $skillLevel = $playerDetails['games']['cs2']['skill_level'] ?? null;

            if (! $steamId || $elo === null || $skillLevel === null) {
                return null;
            }

            return [
                'faceit_id' => $faceitId,
                'steam_id' => $steamId,
                'elo' => $elo,
                'skill_level' => $skillLevel,
            ];
        } catch (\Exception $e) {
            Log::channel('parser')->warning('Failed to retrieve FACEIT player data', [
                'faceit_id' => $faceitId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Validate that player counts match and all players are present.
     *
     * @param  array<string, array>  $faceitPlayerData  Player data from FACEIT
     * @param  \Illuminate\Database\Eloquent\Collection  $matchPlayers  Match players from database
     * @param  \App\Models\GameMatch  $gameMatch  The game match
     * @return bool True if validation passes
     */
    private function validatePlayerCounts(array $faceitPlayerData, $matchPlayers, \App\Models\GameMatch $gameMatch): bool
    {
        $faceitSteamIds = array_keys($faceitPlayerData);
        $matchSteamIds = $matchPlayers->pluck('player.steam_id')->filter()->toArray();

        if (count($faceitSteamIds) !== 10 || count($matchSteamIds) !== 10) {
            Log::channel('parser')->warning('Player count mismatch in FACEIT match validation', [
                'faceit_player_count' => count($faceitSteamIds),
                'match_player_count' => count($matchSteamIds),
                'game_match_id' => $gameMatch->id,
            ]);

            return false;
        }

        $missingPlayers = array_diff($matchSteamIds, $faceitSteamIds);
        if (! empty($missingPlayers)) {
            Log::channel('parser')->warning('Not all match players found in FACEIT response', [
                'missing_steam_ids' => $missingPlayers,
                'game_match_id' => $gameMatch->id,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Update player faceit_id and process ranks.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $matchPlayers  Match players from database
     * @param  array<string, array>  $faceitPlayerData  Player data from FACEIT
     * @param  \App\Models\GameMatch  $gameMatch  The game match
     */
    private function updatePlayersAndRanks($matchPlayers, array $faceitPlayerData, \App\Models\GameMatch $gameMatch): void
    {
        foreach ($matchPlayers as $matchPlayer) {
            $player = $matchPlayer->player;

            if (! $player || ! $player->steam_id) {
                continue;
            }

            $playerData = $faceitPlayerData[$player->steam_id] ?? null;
            if (! $playerData) {
                continue;
            }

            $this->updatePlayerFaceitId($player, $playerData['faceit_id']);
            $this->updatePlayerRank($player, $playerData['elo'], $playerData['skill_level'], $gameMatch);
        }
    }

    /**
     * Update player's FACEIT ID if it differs.
     *
     * @param  \App\Models\Player  $player  The player
     * @param  string  $faceitId  The FACEIT ID
     */
    private function updatePlayerFaceitId(Player $player, string $faceitId): void
    {
        if ($player->faceit_id !== $faceitId) {
            $player->update(['faceit_id' => $faceitId]);
        }
    }

    /**
     * Update player rank if elo has changed.
     *
     * @param  \App\Models\Player  $player  The player
     * @param  int  $elo  The current FACEIT elo
     * @param  int  $skillLevel  The current FACEIT skill level
     * @param  \App\Models\GameMatch  $gameMatch  The game match
     */
    private function updatePlayerRank(Player $player, int $elo, int $skillLevel, \App\Models\GameMatch $gameMatch): void
    {
        // Get most recent FaceIT rank for this player
        $latestRank = PlayerRank::where('player_id', $player->id)
            ->where('rank_type', 'faceit')
            ->orderBy('created_at', 'desc')
            ->first();

        // If no rank exists or elo differs, create new record
        if (! $latestRank || $latestRank->rank_value !== $elo) {
            PlayerRank::create([
                'player_id' => $player->id,
                'rank_type' => 'faceit',
                'map' => null, // FACEIT elo is global, not map-specific
                'rank' => (string) $skillLevel,
                'rank_value' => $elo,
                'created_at' => $gameMatch->created_at ?? now(),
                'updated_at' => $gameMatch->created_at ?? now(),
            ]);
        }
    }
}
