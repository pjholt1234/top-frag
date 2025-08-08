<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PlayerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'message' => 'List of players',
            'data' => [],
            'meta' => [
                'total' => 0,
                'per_page' => 15,
                'current_page' => 1,
                'last_page' => 1
            ]
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        return response()->json([
            'message' => 'Player details',
            'data' => [
                'id' => $id,
                'steam_id' => 'STEAM_0:1:12345678',
                'name' => 'SamplePlayer',
                'total_matches' => 25,
                'first_seen_at' => '2024-01-01T00:00:00Z',
                'last_seen_at' => '2024-12-01T00:00:00Z'
            ]
        ]);
    }

    /**
     * Get matches for a specific player.
     */
    public function matches(string $id): JsonResponse
    {
        return response()->json([
            'message' => 'Player matches',
            'data' => [
                'player_id' => $id,
                'matches' => []
            ],
            'meta' => [
                'total' => 0,
                'per_page' => 15,
                'current_page' => 1,
                'last_page' => 1
            ]
        ]);
    }

    /**
     * Get stats for a specific player.
     */
    public function stats(string $id): JsonResponse
    {
        return response()->json([
            'message' => 'Player statistics',
            'data' => [
                'player_id' => $id,
                'total_kills' => 0,
                'total_deaths' => 0,
                'kd_ratio' => 0.0,
                'headshot_percentage' => 0.0,
                'total_matches' => 0,
                'average_damage_per_round' => 0.0
            ]
        ]);
    }
}
