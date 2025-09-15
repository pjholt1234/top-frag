<?php

namespace App\Services\Matches;

use App\Enums\GrenadeType;
use App\Enums\MapType;
use App\Enums\PlayerSide;
use App\Models\GameMatch;
use App\Models\GrenadeEvent;
use App\Models\Player;
use App\Services\MatchCacheManager;

class GrenadeExplorerService
{
    use MatchAccessTrait;

    public function getExplorer(array $filters, int $matchId): array
    {
        $cacheKey = $this->getCacheKey($filters);

        return MatchCacheManager::remember($cacheKey, $matchId, function () use ($filters, $matchId) {
            return $this->buildExplorer($filters, $matchId);
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

    private function buildExplorer(array $filters, int $matchId): array
    {
        $map = $filters['map'] ?? null;
        $roundNumber = $filters['round_number'] ?? null;
        $grenadeType = $filters['grenade_type'] ?? null;
        $playerSteamId = $filters['player_steam_id'] ?? null;
        $playerSide = $filters['player_side'] ?? null;

        $query = GrenadeEvent::query()
            ->join('matches', 'grenade_events.match_id', '=', 'matches.id')
            ->join('match_players', 'matches.id', '=', 'match_players.match_id')
            ->join('players as match_players_player', 'match_players.player_id', '=', 'match_players_player.id')
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

    public function getFilterOptions(array $filters, int $matchId): array
    {
        $cacheKey = $this->getFilterOptionsCacheKey($filters);

        return MatchCacheManager::remember($cacheKey, $matchId, function () use ($filters, $matchId) {
            return $this->buildFilterOptions($filters, $matchId);
        });
    }

    private function buildFilterOptions(array $filters, int $matchId): array
    {
        $map = $filters['map'] ?? null;
        $matches = [];
        if ($map) {
            $matches = GameMatch::query()
                ->join('match_players', 'matches.id', '=', 'match_players.match_id')
                ->join('players', 'match_players.player_id', '=', 'players.id')
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
                ->where('matches.id', $matchId)
                ->select('players.steam_id', 'players.name')
                ->distinct()
                ->orderBy('players.name')
                ->get()
                ->toArray();
        }

        return [
            'maps' => MapType::options(),
            'matches' => $matches,
            'rounds' => $rounds,
            'grenadeTypes' => GrenadeType::options(),
            'players' => $players,
            'playerSides' => PlayerSide::options(),
        ];
    }
}
