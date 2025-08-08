<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class StatsController extends Controller
{
    /**
     * Get match statistics.
     */
    public function matches(): JsonResponse
    {
        return response()->json([
            'message' => 'Match statistics',
            'data' => [
                'total_matches' => 0,
                'matches_this_month' => 0,
                'average_match_duration' => 0,
                'most_played_maps' => [],
                'match_types_distribution' => []
            ]
        ]);
    }

    /**
     * Get player statistics.
     */
    public function players(): JsonResponse
    {
        return response()->json([
            'message' => 'Player statistics',
            'data' => [
                'total_players' => 0,
                'active_players_this_month' => 0,
                'new_players_this_month' => 0,
                'average_matches_per_player' => 0
            ]
        ]);
    }

    /**
     * Get leaderboards.
     */
    public function leaderboards(): JsonResponse
    {
        return response()->json([
            'message' => 'Leaderboards',
            'data' => [
                'top_killers' => [],
                'top_aimers' => [],
                'top_utility_players' => [],
                'top_clutch_players' => [],
                'most_consistent_players' => []
            ]
        ]);
    }
}
