<?php

namespace App\Actions;

use App\Models\Player;
use App\Models\PlayerRank;
use App\Models\User;
use App\Services\FaceITRepository;
use Illuminate\Support\Facades\Log;

class FetchAndStoreFaceITProfileAction
{
    public function __construct(
        private readonly FaceITRepository $faceITRepository
    ) {}

    /**
     * Fetch and store FACEIT profile data for a user
     */
    public function execute(User $user): void
    {
        if (! $user->steam_id) {
            return;
        }

        try {
            // Get FACEIT player data by Steam ID
            $faceitData = $this->faceITRepository->getPlayerBySteamId($user->steam_id, 'cs2');

            // Extract player_id and nickname
            $faceitPlayerId = $faceitData['player_id'] ?? null;
            $faceitNickname = $faceitData['nickname'] ?? null;

            if (! $faceitPlayerId || ! $faceitNickname) {
                Log::warning('FACEIT data missing required fields', [
                    'user_id' => $user->id,
                    'steam_id' => $user->steam_id,
                    'faceit_data' => $faceitData,
                ]);

                return;
            }

            // Extract skill_level and faceit_elo from games.cs2
            $skillLevel = $faceitData['games']['cs2']['skill_level'] ?? null;
            $faceitElo = $faceitData['games']['cs2']['faceit_elo'] ?? null;

            if ($skillLevel === null || $faceitElo === null) {
                Log::warning('FACEIT CS2 game data missing', [
                    'user_id' => $user->id,
                    'steam_id' => $user->steam_id,
                    'games' => $faceitData['games'] ?? null,
                ]);

                return;
            }

            // Update user with FACEIT player_id and nickname
            $user->update([
                'faceit_player_id' => $faceitPlayerId,
                'faceit_nickname' => $faceitNickname,
            ]);

            // Get or create the Player record
            $player = Player::firstOrCreate(
                ['steam_id' => $user->steam_id],
                ['name' => $faceitNickname]
            );

            // Store rank data in player_ranks table
            PlayerRank::create(
                [
                    'player_id' => $player->id,
                    'rank_type' => 'faceit',
                    'map' => null,
                    'rank' => (string) $skillLevel,
                    'rank_value' => $faceitElo,
                ]
            );

            Log::info('Updated FACEIT profile for user', [
                'user_id' => $user->id,
                'steam_id' => $user->steam_id,
                'faceit_player_id' => $faceitPlayerId,
                'faceit_nickname' => $faceitNickname,
                'skill_level' => $skillLevel,
                'faceit_elo' => $faceitElo,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch FACEIT profile for user', [
                'user_id' => $user->id,
                'steam_id' => $user->steam_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
