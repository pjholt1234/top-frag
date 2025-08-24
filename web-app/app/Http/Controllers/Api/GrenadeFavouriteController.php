<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateGrenadeFavouriteRequest;
use App\Models\GrenadeFavourite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GrenadeFavouriteController extends Controller
{
    /**
     * Get all grenade favourites for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        $query = $user->grenadeFavourites()
            ->with(['match' => function ($query) {
                $query->select('id', 'map', 'start_timestamp', 'end_timestamp');
            }]);

        // Apply filters if provided
        if ($request->has('match_id')) {
            $query->where('match_id', $request->get('match_id'));
        }

        if ($request->has('grenade_type')) {
            $query->where('grenade_type', $request->get('grenade_type'));
        }

        if ($request->has('player_side')) {
            $query->where('player_side', $request->get('player_side'));
        }

        $favourites = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'favourites' => $favourites,
        ]);
    }

    /**
     * Create a new grenade favourite
     */
    public function create(CreateGrenadeFavouriteRequest $request): JsonResponse
    {
        $user = Auth::user();

        // Check if this grenade is already favourited by this user
        $existingFavourite = $user->grenadeFavourites()
            ->where('match_id', $request->match_id)
            ->where('round_number', $request->round_number)
            ->where('tick_timestamp', $request->tick_timestamp)
            ->where('player_steam_id', $request->player_steam_id)
            ->first();

        if ($existingFavourite) {
            return response()->json([
                'message' => 'This grenade is already in your favourites',
            ], 409);
        }

        $favourite = $user->grenadeFavourites()->create($request->all());

        return response()->json([
            'message' => 'Grenade added to favourites successfully',
            'favourite' => $favourite->load('match'),
        ], 201);
    }

    /**
     * Check if a specific grenade is favourited by the user
     */
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

        if (!$favourite) {
            return response()->json([
                'message' => 'Favourite not found',
            ], 404);
        }

        $favourite->delete();

        return response()->json([
            'message' => 'Favourite removed successfully',
        ]);
    }
}
