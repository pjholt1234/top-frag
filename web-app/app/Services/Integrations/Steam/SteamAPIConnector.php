<?php

namespace App\Services\Integrations\Steam;

use App\Services\Infrastructure\RateLimiterService;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SteamAPIConnector
{
    private ?string $apiKey = null;

    private string $baseUrl = 'https://api.steampowered.com';

    public function __construct()
    {
        $this->apiKey = config('services.steam.api_key');
    }

    public function checkServiceHealth(): bool
    {
        try {
            $response = Http::timeout(10)->get($this->baseUrl.'/ISteamWebAPIUtil/GetServerInfo/v1/');

            if (! $response->successful()) {
                Log::warning('Steam API health check failed', [
                    'status_code' => $response->getStatusCode(),
                    'response' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (Exception $e) {
            Log::error('Steam API health check exception', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getNextMatchSharingCode(string $steamId, string $steamGameAuthCode, string $knownSharecode): ?string
    {
        if (! $this->apiKey) {
            Log::error('Steam API key not configured');

            return null;
        }

        $this->checkRateLimit();

        try {
            $response = Http::timeout(30)
                ->get($this->baseUrl.'/ICSGOPlayers_730/GetNextMatchSharingCode/v1/', [
                    'key' => $this->apiKey,
                    'steamid' => $steamId,
                    'steamidkey' => $steamGameAuthCode,
                    'knowncode' => $knownSharecode,
                ]);

            if (! $response->successful()) {
                Log::warning('Steam API request failed', [
                    'status_code' => $response->getStatusCode(),
                    'response' => $response->body(),
                    'steam_id' => $steamId,
                    'steam_game_auth_code' => $steamGameAuthCode,
                    'known_sharecode' => $knownSharecode,
                ]);

                return null;
            }

            $data = $response->json();

            if (! isset($data['result']['nextcode'])) {
                Log::info('No next sharecode available', [
                    'steam_id' => $steamId,
                    'known_sharecode' => $knownSharecode,
                    'response' => $data,
                ]);

                return null;
            }

            $nextSharecode = $data['result']['nextcode'];

            Log::info('Retrieved next sharecode from Steam API', [
                'steam_id' => $steamId,
                'known_sharecode' => $knownSharecode,
                'next_sharecode' => $nextSharecode,
            ]);

            return $nextSharecode;
        } catch (Exception $e) {
            Log::error('Steam API request exception', [
                'error' => $e->getMessage(),
                'steam_id' => $steamId,
                'steam_game_auth_code' => $steamGameAuthCode,
                'known_sharecode' => $knownSharecode,
            ]);

            return null;
        }
    }

    public function getPlayerSummaries(array $steamIds): ?array
    {
        if (! $this->apiKey) {
            Log::error('Steam API key not configured');

            return null;
        }

        if (empty($steamIds)) {
            return [];
        }

        $this->checkRateLimit();

        try {
            $steamIdsParam = implode(',', $steamIds);

            $response = Http::timeout(30)
                ->get($this->baseUrl.'/ISteamUser/GetPlayerSummaries/v0002/', [
                    'key' => $this->apiKey,
                    'steamids' => $steamIdsParam,
                ]);

            if (! $response->successful()) {
                Log::warning('Steam API GetPlayerSummaries request failed', [
                    'status_code' => $response->getStatusCode(),
                    'response' => $response->body(),
                    'steam_ids' => $steamIds,
                ]);

                return null;
            }

            $data = $response->json();

            if (! isset($data['response']['players'])) {
                Log::info('No player data returned from Steam API', [
                    'steam_ids' => $steamIds,
                    'response' => $data,
                ]);

                return [];
            }

            $players = [];
            foreach ($data['response']['players'] as $player) {
                $players[$player['steamid']] = [
                    'steam_id' => $player['steamid'],
                    'persona_name' => $player['personaname'] ?? '',
                    'profile_url' => $player['profileurl'] ?? '',
                    'avatar' => $player['avatar'] ?? '',
                    'avatar_medium' => $player['avatarmedium'] ?? '',
                    'avatar_full' => $player['avatarfull'] ?? '',
                    'persona_state' => $player['personastate'] ?? 0,
                    'community_visibility_state' => $player['communityvisibilitystate'] ?? 0,
                ];
            }

            Log::info('Retrieved player summaries from Steam API', [
                'steam_ids' => $steamIds,
                'players_found' => count($players),
            ]);

            return $players;
        } catch (Exception $e) {
            Log::error('Steam API GetPlayerSummaries exception', [
                'error' => $e->getMessage(),
                'steam_ids' => $steamIds,
            ]);

            return null;
        }
    }

    private function checkRateLimit(): void
    {
        $rateLimiter = app(RateLimiterService::class);

        if (! $rateLimiter->checkSteamApiLimit()) {
            Log::warning('Steam API rate limit reached, waiting');
            $rateLimiter->waitForRateLimit('steam_api', 100, 300);
        }
    }
}
