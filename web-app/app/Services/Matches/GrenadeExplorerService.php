<?php

namespace App\Services\Matches;

use App\Enums\GrenadeType;
use App\Models\GameMatch;
use App\Models\GrenadeEvent;
use App\Models\Player;
use App\Models\User;
use App\Services\MatchCacheManager;

class GrenadeExplorerService
{
    use MatchAccessTrait;

    public function getExplorer(User $user, array $filters, int $matchId): array
    {
        $cacheKey = $this->getCacheKey($filters);

        return MatchCacheManager::remember($cacheKey, $matchId, function () use ($user, $filters, $matchId) {
            return $this->buildExplorer($user, $filters, $matchId);
        });
    }

    private function getCacheKey(array $filters): string
    {
        $filterHash = empty($filters) ? 'default' : md5(serialize($filters));

        return "grenade-explorer_{$filterHash}";
    }

    private function getFilterOptionsCacheKey(array $filters): string
    {
        $filterHash = empty($filters) ? 'default' : md5(serialize($filters));

        return "grenade-explorer-filter-options_{$filterHash}";
    }

    private function buildExplorer(User $user, array $filters, int $matchId): array
    {
        $map = $filters['map'] ?? null;
        $roundNumber = $filters['round_number'] ?? null;
        $grenadeType = $filters['grenade_type'] ?? null;
        $playerSteamId = $filters['player_steam_id'] ?? null;
        $playerSide = $filters['player_side'] ?? null;

        // Start with base query for user's matches (same as controller)
        $query = GrenadeEvent::query()
            ->join('matches', 'grenade_events.match_id', '=', 'matches.id')
            ->join('match_players', 'matches.id', '=', 'match_players.match_id')
            ->join('players as match_players_player', 'match_players.player_id', '=', 'match_players_player.id')
            ->where('match_players_player.steam_id', $user->steam_id)
            ->select('grenade_events.*', 'matches.map', 'players.name as player_name')
            ->join('players', 'grenade_events.player_steam_id', '=', 'players.steam_id');

        // Apply filters (same as controller)
        if ($map) {
            $query->where('matches.map', $map);
        }

        if ($matchId) {
            $query->where('grenade_events.match_id', $matchId);
        }

        if ($roundNumber && $roundNumber !== 'all') {
            $query->where('grenade_events.round_number', $roundNumber);
        }

        if ($grenadeType) {
            if ($grenadeType === 'fire_grenades') {
                // Special case: Fire Grenades (Molotov + Incendiary)
                $query->whereIn('grenade_events.grenade_type', [
                    GrenadeType::MOLOTOV->value,
                    GrenadeType::INCENDIARY->value,
                ]);
            } else {
                $query->where('grenade_events.grenade_type', $grenadeType);
            }
        }

        if ($playerSteamId && $playerSteamId !== 'all') {
            $query->where('grenade_events.player_steam_id', $playerSteamId);
        }

        if ($playerSide && $playerSide !== 'all') {
            $query->where('grenade_events.player_side', $playerSide);
        }

        $grenades = $query->get();

        return [
            'grenades' => $grenades,
            'filters' => $filters,
        ];
    }

    public function getFilterOptions(User $user, array $filters, int $matchId): array
    {
        $cacheKey = $this->getFilterOptionsCacheKey($filters);

        return MatchCacheManager::remember($cacheKey, $matchId, function () use ($user, $filters, $matchId) {
            return $this->buildFilterOptions($user, $filters, $matchId);
        });
    }

    private function buildFilterOptions(User $user, array $filters, int $matchId): array
    {
        $map = $filters['map'] ?? null;

        // Hardcoded maps as specified (same as controller)
        $maps = [
            ['name' => 'de_ancient', 'displayName' => 'Ancient'],
            ['name' => 'de_dust2', 'displayName' => 'Dust II'],
            ['name' => 'de_mirage', 'displayName' => 'Mirage'],
            ['name' => 'de_inferno', 'displayName' => 'Inferno'],
            ['name' => 'de_nuke', 'displayName' => 'Nuke'],
            ['name' => 'de_overpass', 'displayName' => 'Overpass'],
            ['name' => 'de_train', 'displayName' => 'Train'],
            ['name' => 'de_cache', 'displayName' => 'Cache'],
            ['name' => 'de_anubis', 'displayName' => 'Anubis'],
            ['name' => 'de_vertigo', 'displayName' => 'Vertigo'],
        ];

        // Hardcoded grenade types with special "Fire Grenades" option (same as controller)
        $grenadeTypes = [
            ['type' => 'fire_grenades', 'displayName' => 'Fire Grenades'],
            ['type' => GrenadeType::SMOKE_GRENADE->value, 'displayName' => 'Smoke Grenade'],
            ['type' => GrenadeType::HE_GRENADE->value, 'displayName' => 'HE Grenade'],
            ['type' => GrenadeType::FLASHBANG->value, 'displayName' => 'Flashbang'],
            ['type' => GrenadeType::DECOY->value, 'displayName' => 'Decoy Grenade'],
        ];

        // Hardcoded player sides (same as controller)
        $playerSides = [
            ['side' => 'CT', 'displayName' => 'Counter-Terrorist'],
            ['side' => 'T', 'displayName' => 'Terrorist'],
        ];

        // Dynamic matches based on selected map (same as controller)
        $matches = [];
        if ($map) {
            $matches = GameMatch::query()
                ->join('match_players', 'matches.id', '=', 'match_players.match_id')
                ->join('players', 'match_players.player_id', '=', 'players.id')
                ->where('players.steam_id', $user->steam_id)
                ->where('matches.map', $map)
                ->select('matches.id', 'matches.map')
                ->distinct()
                ->get()
                ->map(function ($match) {
                    return [
                        'id' => $match->id,
                        'name' => "Match #{$match->id} - {$match->map}",
                    ];
                })
                ->toArray();
        }

        // Add "All Matches" option if map is selected and there are matches
        if ($map && ! empty($matches)) {
            array_unshift($matches, [
                'id' => 'all',
                'name' => 'All Matches',
            ]);
        }

        // Dynamic rounds based on selected match (same as controller)
        $rounds = [];
        if ($matchId) {
            $rounds = GrenadeEvent::query()
                ->join('matches', 'grenade_events.match_id', '=', 'matches.id')
                ->join('match_players', 'matches.id', '=', 'match_players.match_id')
                ->join('players', 'match_players.player_id', '=', 'players.id')
                ->where('players.steam_id', $user->steam_id)
                ->where('grenade_events.match_id', $matchId)
                ->select('grenade_events.round_number as number')
                ->distinct()
                ->orderBy('grenade_events.round_number')
                ->get()
                ->toArray();
        }

        // Dynamic players based on selected match (same as controller)
        $players = [];
        if ($matchId) {
            $players = Player::query()
                ->join('match_players', 'players.id', '=', 'match_players.player_id')
                ->join('matches', 'match_players.match_id', '=', 'matches.id')
                ->join('match_players as user_match_players', 'matches.id', '=', 'user_match_players.match_id')
                ->join('players as user_player', 'user_match_players.player_id', '=', 'user_player.id')
                ->where('user_player.steam_id', $user->steam_id)
                ->where('matches.id', $matchId)
                ->select('players.steam_id', 'players.name')
                ->distinct()
                ->orderBy('players.name')
                ->get()
                ->toArray();
        }

        return [
            'maps' => $maps,
            'matches' => $matches,
            'rounds' => $rounds,
            'grenadeTypes' => $grenadeTypes,
            'players' => $players,
            'playerSides' => $playerSides,
        ];
    }
}
