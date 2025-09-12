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

    public function getExplorer(User $user, int $matchId, array $filters = []): array
    {
        $cacheKey = $this->getCacheKey($filters);

        return MatchCacheManager::remember($cacheKey, $matchId, function () use ($user, $matchId, $filters) {
            return $this->buildExplorer($user, $matchId, $filters);
        });
    }

    private function getCacheKey(array $filters): string
    {
        $filterHash = empty($filters) ? 'default' : md5(serialize($filters));
        return "grenade-explorer_{$filterHash}";
    }

    private function buildExplorer(User $user, int $matchId, array $filters): array
    {
        // Check user access first
        if (!$this->hasUserAccessToMatch($user, $matchId)) {
            return [];
        }

        $query = GrenadeEvent::query()
            ->where('match_id', $matchId)
            ->with('player');

        // Apply filters
        if (!empty($filters['round_number']) && $filters['round_number'] !== 'all') {
            $query->where('round_number', $filters['round_number']);
        }

        if (!empty($filters['grenade_type'])) {
            if ($filters['grenade_type'] === 'fire_grenades') {
                // Special case: Fire Grenades (Molotov + Incendiary)
                $query->whereIn('grenade_type', [
                    GrenadeType::MOLOTOV->value,
                    GrenadeType::INCENDIARY->value,
                ]);
            } else {
                $query->where('grenade_type', $filters['grenade_type']);
            }
        }

        if (!empty($filters['player_steam_id']) && $filters['player_steam_id'] !== 'all') {
            $query->where('player_steam_id', $filters['player_steam_id']);
        }

        if (!empty($filters['player_side']) && $filters['player_side'] !== 'all') {
            $query->where('player_side', $filters['player_side']);
        }

        $grenades = $query->get();

        return [
            'grenades' => $grenades,
            'filters' => $filters,
            'stats' => $this->getGrenadeStats($grenades),
        ];
    }

    public function getFilterOptions(User $user, int $matchId): array
    {
        $cacheKey = 'filter-options';

        return MatchCacheManager::remember($cacheKey, $matchId, function () use ($user, $matchId) {
            return $this->buildFilterOptions($user, $matchId);
        });
    }

    private function buildFilterOptions(User $user, int $matchId): array
    {
        // Check user access first
        if (!$this->hasUserAccessToMatch($user, $matchId)) {
            return [];
        }

        // Hardcoded grenade types with special "Fire Grenades" option
        $grenadeTypes = [
            ['type' => 'fire_grenades', 'displayName' => 'Fire Grenades'],
            ['type' => GrenadeType::SMOKE_GRENADE->value, 'displayName' => 'Smoke Grenade'],
            ['type' => GrenadeType::HE_GRENADE->value, 'displayName' => 'HE Grenade'],
            ['type' => GrenadeType::FLASHBANG->value, 'displayName' => 'Flashbang'],
            ['type' => GrenadeType::DECOY->value, 'displayName' => 'Decoy Grenade'],
        ];

        // Hardcoded player sides
        $playerSides = [
            ['side' => 'CT', 'displayName' => 'Counter-Terrorist'],
            ['side' => 'T', 'displayName' => 'Terrorist'],
        ];

        // Dynamic rounds based on match
        $rounds = GrenadeEvent::query()
            ->where('match_id', $matchId)
            ->select('round_number as number')
            ->distinct()
            ->orderBy('round_number')
            ->get()
            ->toArray();

        // Add "All Rounds" option
        array_unshift($rounds, [
            'number' => 'all',
            'displayName' => 'All Rounds',
        ]);

        // Dynamic players based on match
        $players = Player::query()
            ->join('match_players', 'players.id', '=', 'match_players.player_id')
            ->where('match_players.match_id', $matchId)
            ->select('players.steam_id', 'players.name')
            ->distinct()
            ->orderBy('players.name')
            ->get()
            ->toArray();

        // Add "All Players" option
        array_unshift($players, [
            'steam_id' => 'all',
            'name' => 'All Players',
        ]);

        return [
            'rounds' => $rounds,
            'grenadeTypes' => $grenadeTypes,
            'players' => $players,
            'playerSides' => $playerSides,
        ];
    }

    private function getGrenadeStats($grenades): array
    {
        if ($grenades->isEmpty()) {
            return [
                'total_grenades' => 0,
                'by_type' => [],
                'by_round' => [],
            ];
        }

        $byType = $grenades->groupBy('grenade_type')->map(function ($group) {
            return [
                'type' => $group->first()->grenade_type,
                'count' => $group->count(),
                'avg_effectiveness' => $group->where('effectiveness_rating', '>', 0)->avg('effectiveness_rating') ?? 0,
            ];
        })->values();

        $byRound = $grenades->groupBy('round_number')->map(function ($group) {
            return [
                'round' => $group->first()->round_number,
                'count' => $group->count(),
                'avg_effectiveness' => $group->where('effectiveness_rating', '>', 0)->avg('effectiveness_rating') ?? 0,
            ];
        })->values();

        return [
            'total_grenades' => $grenades->count(),
            'by_type' => $byType,
            'by_round' => $byRound,
        ];
    }
}
