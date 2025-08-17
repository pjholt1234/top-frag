<?php

namespace App\Http\Controllers\Api;

use App\Enums\GrenadeType;
use App\Http\Controllers\Controller;
use App\Models\GameMatch;
use App\Models\GrenadeEvent;
use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GrenadeLibraryController extends Controller
{
    /**
     * Get grenade data with filters
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Get filter parameters
        $map = $request->get('map');
        $matchId = $request->get('match_id');
        $roundNumber = $request->get('round_number');
        $grenadeType = $request->get('grenade_type');
        $playerSteamId = $request->get('player_steam_id');
        $playerSide = $request->get('player_side');

        // Start with base query for user's matches
        $query = GrenadeEvent::query()
            ->join('matches', 'grenade_events.match_id', '=', 'matches.id')
            ->join('match_players', 'matches.id', '=', 'match_players.match_id')
            ->join('players as match_players_player', 'match_players.player_id', '=', 'match_players_player.id')
            ->where('match_players_player.steam_id', $user->steam_id)
            ->select('grenade_events.*', 'matches.map', 'players.name as player_name')
            ->join('players', 'grenade_events.player_steam_id', '=', 'players.steam_id');

        // Apply filters
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

        return response()->json([
            'grenades' => $grenades,
            'filters' => [
                'map' => $map,
                'match_id' => $matchId,
                'round_number' => $roundNumber,
                'grenade_type' => $grenadeType,
                'player_steam_id' => $playerSteamId,
                'player_side' => $playerSide,
            ],
        ]);
    }

    /**
     * Get filter options for the grenade library
     */
    public function filterOptions(Request $request): JsonResponse
    {
        $user = Auth::user();
        $map = $request->get('map');
        $matchId = $request->get('match_id');

        // Hardcoded maps as specified
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

        // Dynamic matches based on selected map
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

        // Dynamic rounds based on selected match
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

        // Dynamic players based on selected match
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

        return response()->json([
            'maps' => $maps,
            'matches' => $matches,
            'rounds' => $rounds,
            'grenadeTypes' => $grenadeTypes,
            'players' => $players,
            'playerSides' => $playerSides,
        ]);
    }
}
