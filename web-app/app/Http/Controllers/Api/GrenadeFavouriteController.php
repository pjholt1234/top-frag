<?php

namespace App\Http\Controllers\Api;

use App\Enums\GrenadeType;
use App\Enums\MapType;
use App\Enums\PlayerSide;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateGrenadeFavouriteRequest;
use App\Models\GameMatch;
use App\Models\GrenadeFavourite;
use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GrenadeFavouriteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        $map = $request->get('map');
        $matchId = $request->get('match_id');
        $roundNumber = $request->get('round_number');
        $grenadeType = $request->get('grenade_type');
        $playerSteamId = $request->get('player_steam_id');
        $playerSide = $request->get('player_side');

        $query = GrenadeFavourite::query()
            ->join('matches', 'grenade_favourites.match_id', '=', 'matches.id')
            ->where('grenade_favourites.user_id', $user->id)
            ->select('grenade_favourites.*', 'matches.map', 'players.name as player_name')
            ->join('players', 'grenade_favourites.player_steam_id', '=', 'players.steam_id');

        // Apply filters
        if ($map) {
            $query->where('matches.map', $map);
        }

        if ($matchId) {
            $query->where('grenade_favourites.match_id', $matchId);
        }

        if ($roundNumber && $roundNumber !== 'all') {
            $query->where('grenade_favourites.round_number', $roundNumber);
        }

        if ($grenadeType) {
            if ($grenadeType === 'fire_grenades') {
                $query->whereIn('grenade_favourites.grenade_type', [
                    GrenadeType::MOLOTOV->value,
                    GrenadeType::INCENDIARY->value,
                ]);
            } else {
                $query->where('grenade_favourites.grenade_type', $grenadeType);
            }
        }

        if ($playerSteamId && $playerSteamId !== 'all') {
            $query->where('grenade_favourites.player_steam_id', $playerSteamId);
        }

        if ($playerSide && $playerSide !== 'all') {
            $query->where('grenade_favourites.player_side', $playerSide);
        }

        $favourites = $query->orderBy('grenade_favourites.created_at', 'desc')->get();

        return response()->json([
            'grenades' => $favourites,
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

    public function filterOptions(Request $request): JsonResponse
    {
        $user = Auth::user();
        $map = $request->get('map');
        $matchId = $request->get('match_id');

        $matches = [];
        if ($map) {
            $matches = GameMatch::query()
                ->join('grenade_favourites', 'matches.id', '=', 'grenade_favourites.match_id')
                ->where('grenade_favourites.user_id', $user->id)
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

        if ($map && ! empty($matches)) {
            array_unshift($matches, [
                'id' => 'all',
                'name' => 'All Matches',
            ]);
        }

        $rounds = [];
        if ($matchId) {
            $rounds = GrenadeFavourite::query()
                ->join('matches', 'grenade_favourites.match_id', '=', 'matches.id')
                ->where('grenade_favourites.user_id', $user->id)
                ->where('grenade_favourites.match_id', $matchId)
                ->select('grenade_favourites.round_number as number')
                ->distinct()
                ->orderBy('grenade_favourites.round_number')
                ->get()
                ->toArray();
        }

        $players = [];
        if ($matchId) {
            $players = Player::query()
                ->join('grenade_favourites', 'players.steam_id', '=', 'grenade_favourites.player_steam_id')
                ->join('matches', 'grenade_favourites.match_id', '=', 'matches.id')
                ->where('grenade_favourites.user_id', $user->id)
                ->where('matches.id', $matchId)
                ->select('players.steam_id', 'players.name')
                ->distinct()
                ->orderBy('players.name')
                ->get()
                ->toArray();
        }

        return response()->json([
            'maps' => MapType::options(),
            'matches' => $matches,
            'rounds' => $rounds,
            'grenadeTypes' => GrenadeType::options(),
            'players' => $players,
            'playerSides' => PlayerSide::options(),
        ]);
    }

    public function create(CreateGrenadeFavouriteRequest $request): JsonResponse
    {
        $user = Auth::user();

        $existingFavourite = $user->grenadeFavourites()
            ->where('match_id', $request->match_id)
            ->where('round_number', $request->round_number)
            ->where('tick_timestamp', $request->tick_timestamp)
            ->where('player_steam_id', $request->player_steam_id)
            ->first();

        if ($existingFavourite) {
            return response()->json([
                'message' => config('messaging.grenade.favourites.duplicate-error'),
            ], 409);
        }

        $favourite = $user->grenadeFavourites()->create($request->all());

        if (! $favourite) {
            return response()->json([
                'message' => config('messaging.grenade.favourites.not-found-error'),
                'favourite' => null,
            ], 500);
        }

        return response()->json([
            'message' => config('messaging.grenade.favourites.created'),
            'favourite' => $favourite->load('match'),
        ], 201);
    }

    public function check(Request $request): JsonResponse
    {
        $user = Auth::user();

        $favourite = $user->grenadeFavourites()
            ->where('match_id', $request->match_id)
            ->where('round_number', $request->round_number)
            ->where('tick_timestamp', $request->tick_timestamp)
            ->where('player_steam_id', $request->player_steam_id)
            ->first();

        return response()->json([
            'is_favourited' => $favourite !== null,
            'favourite_id' => $favourite ? $favourite->id : null,
        ]);
    }

    /**
     * Delete a grenade favourite
     */
    public function delete(Request $request, $id): JsonResponse
    {
        $user = Auth::user();

        $favourite = $user->grenadeFavourites()->find($id);

        if (! $favourite) {
            return response()->json([
                'message' => config('messaging.grenade.favourites.not-found-error'),
            ], 404);
        }

        $favourite->delete();

        return response()->json([
            'message' => 'Favourite removed successfully',
        ]);
    }
}
