<?php

namespace App\Services;

use App\Exceptions\FaceITAPIConnectorException;

class FaceITRepository
{
    public function __construct(
        private readonly FaceITAPIConnector $connector
    ) {}

    /**
     * Get player details by Steam ID.
     *
     * @param  string  $steamId  The Steam ID (game_player_id)
     * @param  string  $game  The game identifier (e.g., 'cs2', 'csgo')
     * @return array<string, mixed>
     *
     * @throws FaceITAPIConnectorException
     */
    public function getPlayerBySteamId(string $steamId, string $game = 'cs2'): array
    {
        return $this->connector->get('players', [
            'game_player_id' => $steamId,
            'game' => $game,
        ]);
    }

    /**
     * Get player details by nickname.
     *
     * @param  string  $nickname  The player's FACEIT nickname
     * @return array<string, mixed>
     *
     * @throws FaceITAPIConnectorException
     */
    public function getPlayerByNickname(string $nickname): array
    {
        return $this->connector->get('players', [
            'nickname' => $nickname,
        ]);
    }

    /**
     * Get match history for a player.
     *
     * @param  string  $playerId  The FACEIT player ID
     * @param  string|null  $game  The game identifier (e.g., 'cs2', 'csgo'). Optional.
     * @param  int  $offset  The starting item position (default: 0)
     * @param  int  $limit  The number of items to return, 1-100 (default: 20)
     * @return array<string, mixed>
     *
     * @throws FaceITAPIConnectorException
     */
    public function getPlayerMatchHistory(string $playerId, ?string $game = null, int $offset = 0, int $limit = 20): array
    {
        $queryParams = [
            'offset' => max(0, $offset),
            'limit' => max(1, min(100, $limit)),
        ];

        if ($game !== null) {
            $queryParams['game'] = $game;
        }

        return $this->connector->get("players/{$playerId}/history", $queryParams);
    }
}
