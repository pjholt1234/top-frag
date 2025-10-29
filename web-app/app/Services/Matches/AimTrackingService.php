<?php

namespace App\Services\Matches;

use App\Models\GameMatch;
use App\Models\PlayerMatchAimEvent;
use App\Models\PlayerMatchAimWeaponEvent;
use App\Models\User;
use App\Services\MatchCacheManager;

class AimTrackingService
{
    use MatchAccessTrait;

    public function getFilterOptions(User $user, array $filters, int $matchId): array
    {
        $cacheKey = $this->getFilterOptionsCacheKey($filters);

        return MatchCacheManager::remember($cacheKey, $matchId, function () use ($user, $filters, $matchId) {
            return $this->buildFilterOptions($user, $filters, $matchId);
        });
    }

    public function get(User $user, array $filters, int $matchId): array
    {
        $cacheKey = $this->getCacheKey($filters);

        return MatchCacheManager::remember($cacheKey, $matchId, function () use ($user, $filters, $matchId) {
            return $this->buildAimTracking($user, $filters, $matchId);
        });
    }

    public function getWeaponStats(User $user, array $filters, int $matchId): array
    {
        $cacheKey = $this->getWeaponCacheKey($filters);

        return MatchCacheManager::remember($cacheKey, $matchId, function () use ($user, $filters, $matchId) {
            return $this->buildWeaponStats($user, $filters, $matchId);
        });
    }

    private function getCacheKey(array $filters): string
    {
        $filterHash = empty($filters) ? 'default' : md5(serialize($filters));

        return "aim-tracking_{$filterHash}";
    }

    private function getWeaponCacheKey(array $filters): string
    {
        $filterHash = empty($filters) ? 'default' : md5(serialize($filters));

        return "aim-tracking-weapon_{$filterHash}";
    }

    private function getFilterOptionsCacheKey(array $filters): string
    {
        $filterHash = empty($filters) ? 'default' : md5(serialize($filters));

        return "aim-tracking-filter-options_{$filterHash}";
    }

    private function buildAimTracking(User $user, array $filters, int $matchId): array
    {
        $match = GameMatch::find($matchId);

        if (! $match) {
            return [];
        }

        if (empty($filters['player_steam_id'])) {
            return [];
        }

        $playerMatchAimEvent = PlayerMatchAimEvent::query()
            ->where('match_id', $matchId)
            ->where('player_steam_id', $filters['player_steam_id'])
            ->first();

        if (! $playerMatchAimEvent) {
            return [];
        }

        return [
            'match_id' => $playerMatchAimEvent->match_id,
            'player_steam_id' => $playerMatchAimEvent->player_steam_id,
            'shots_fired' => $playerMatchAimEvent->shots_fired,
            'shots_hit' => $playerMatchAimEvent->shots_hit,
            'accuracy_all_shots' => $playerMatchAimEvent->accuracy_all_shots,
            'spraying_shots_fired' => $playerMatchAimEvent->spraying_shots_fired,
            'spraying_shots_hit' => $playerMatchAimEvent->spraying_shots_hit,
            'spraying_accuracy' => $playerMatchAimEvent->spraying_accuracy,
            'average_crosshair_placement_x' => $playerMatchAimEvent->average_crosshair_placement_x,
            'average_crosshair_placement_y' => $playerMatchAimEvent->average_crosshair_placement_y,
            'headshot_accuracy' => $playerMatchAimEvent->headshot_accuracy,
            'average_time_to_damage' => $playerMatchAimEvent->average_time_to_damage,
            'head_hits_total' => $playerMatchAimEvent->head_hits_total,
            'upper_chest_hits_total' => $playerMatchAimEvent->upper_chest_hits_total,
            'chest_hits_total' => $playerMatchAimEvent->chest_hits_total,
            'legs_hits_total' => $playerMatchAimEvent->legs_hits_total,
            'aim_rating' => $playerMatchAimEvent->aim_rating,
        ];
    }

    private function buildWeaponStats(User $user, array $filters, int $matchId): array
    {
        $match = GameMatch::find($matchId);

        if (! $match) {
            return [];
        }

        if (empty($filters['player_steam_id'])) {
            return [];
        }

        // If no weapon_name is provided, return aggregated data from player_match_aim_events
        if (empty($filters['weapon_name'])) {
            $aimEvent = PlayerMatchAimEvent::query()
                ->where('match_id', $matchId)
                ->where('player_steam_id', $filters['player_steam_id'])
                ->first();

            if (! $aimEvent) {
                return [];
            }

            return [
                'match_id' => $matchId,
                'player_steam_id' => $filters['player_steam_id'],
                'weapon_name' => null,
                'shots_fired' => $aimEvent->shots_fired,
                'shots_hit' => $aimEvent->shots_hit,
                'accuracy_all_shots' => $aimEvent->accuracy_all_shots,
                'spraying_shots_fired' => $aimEvent->spraying_shots_fired,
                'spraying_shots_hit' => $aimEvent->spraying_shots_hit,
                'spraying_accuracy' => $aimEvent->spraying_accuracy,
                'average_crosshair_placement_x' => $aimEvent->average_crosshair_placement_x,
                'average_crosshair_placement_y' => $aimEvent->average_crosshair_placement_y,
                'headshot_accuracy' => $aimEvent->headshot_accuracy,
                'head_hits_total' => $aimEvent->head_hits_total,
                'upper_chest_hits_total' => $aimEvent->upper_chest_hits_total,
                'chest_hits_total' => $aimEvent->chest_hits_total,
                'legs_hits_total' => $aimEvent->legs_hits_total,
            ];
        }

        // If weapon_name is provided, return specific weapon data
        $weaponEvent = PlayerMatchAimWeaponEvent::query()
            ->where('match_id', $matchId)
            ->where('player_steam_id', $filters['player_steam_id'])
            ->where('weapon_name', $filters['weapon_name'])
            ->first();

        if (! $weaponEvent) {
            return [];
        }

        return [
            'match_id' => $matchId,
            'player_steam_id' => $filters['player_steam_id'],
            'weapon_name' => $weaponEvent->weapon_name,
            'shots_fired' => $weaponEvent->shots_fired,
            'shots_hit' => $weaponEvent->shots_hit,
            'accuracy_all_shots' => $weaponEvent->accuracy_all_shots,
            'spraying_shots_fired' => $weaponEvent->spraying_shots_fired,
            'spraying_shots_hit' => $weaponEvent->spraying_shots_hit,
            'spraying_accuracy' => $weaponEvent->spraying_accuracy,
            'average_crosshair_placement_x' => $weaponEvent->average_crosshair_placement_x,
            'average_crosshair_placement_y' => $weaponEvent->average_crosshair_placement_y,
            'headshot_accuracy' => $weaponEvent->headshot_accuracy,
            'head_hits_total' => $weaponEvent->head_hits_total,
            'upper_chest_hits_total' => $weaponEvent->upper_chest_hits_total,
            'chest_hits_total' => $weaponEvent->chest_hits_total,
            'legs_hits_total' => $weaponEvent->legs_hits_total,
        ];
    }

    private function buildFilterOptions(User $user, array $filters, int $matchId): array
    {
        $match = GameMatch::find($matchId);

        if (! $match) {
            return [];
        }

        // Get available players
        $players = $match->players->map(function ($player) {
            return [
                'steam_id' => $player->steam_id,
                'name' => $player->name,
            ];
        })->toArray();

        // Get available weapons for the selected player
        $weapons = [];
        if (! empty($filters['player_steam_id'])) {
            $weaponEvents = PlayerMatchAimWeaponEvent::query()
                ->where('match_id', $matchId)
                ->where('player_steam_id', $filters['player_steam_id'])
                ->select('weapon_name')
                ->distinct()
                ->orderBy('weapon_name')
                ->get();

            $weapons = $weaponEvents->map(function ($event) {
                return [
                    'value' => $event->weapon_name,
                    'label' => $this->getWeaponDisplayName($event->weapon_name),
                ];
            })->toArray();

            // Add "All Weapons" option at the beginning
            array_unshift($weapons, [
                'value' => 'all',
                'label' => 'All Weapons',
            ]);
        }

        return [
            'players' => $players,
            'weapons' => $weapons,
            'current_user_steam_id' => $user->steam_id,
        ];
    }

    private function getWeaponDisplayName(string $weaponName): string
    {
        $weaponNames = [
            'ak47' => 'AK-47',
            'aug' => 'AUG',
            'awp' => 'AWP',
            'bizon' => 'PP-Bizon',
            'cz75a' => 'CZ75-Auto',
            'deagle' => 'Desert Eagle',
            'elite' => 'Dual Berettas',
            'famas' => 'FAMAS',
            'fiveseven' => 'Five-SeveN',
            'g3sg1' => 'G3SG1',
            'galilar' => 'Galil AR',
            'glock' => 'Glock-18',
            'hkp2000' => 'P2000',
            'm249' => 'M249',
            'm4a1' => 'M4A4',
            'm4a1_silencer' => 'M4A1-S',
            'mac10' => 'MAC-10',
            'mag7' => 'MAG-7',
            'mp5sd' => 'MP5-SD',
            'mp7' => 'MP7',
            'mp9' => 'MP9',
            'negev' => 'Negev',
            'nova' => 'Nova',
            'p250' => 'P250',
            'p90' => 'P90',
            'revolver' => 'R8 Revolver',
            'sawedoff' => 'Sawed-Off',
            'scar20' => 'SCAR-20',
            'sg556' => 'SG 553',
            'ssg08' => 'SSG 08',
            'tec9' => 'Tec-9',
            'ump45' => 'UMP-45',
            'usp_silencer' => 'USP-S',
            'xm1014' => 'XM1014',
        ];

        return $weaponNames[$weaponName] ?? ucfirst($weaponName);
    }
}
