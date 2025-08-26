<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UserMatchHistoryService;
use Illuminate\Http\Request;

class MatchController extends Controller
{
    public function __construct(
        private readonly UserMatchHistoryService $userMatchHistoryService,
    ) {}

    public function index(Request $request)
    {
        // todo auth policies
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (empty($user->steam_id)) {
            return response()->json(['message' => 'Player not found'], 404);
        }

        $perPage = $request->get('per_page', 10);
        $page = $request->get('page', 1);

        $perPage = max(1, min(50, (int) $perPage));
        $page = max(1, (int) $page);

        // Extract filter parameters
        $filters = [
            'map' => $request->get('map'),
            'match_type' => $request->get('match_type'),
            'player_was_participant' => $request->get('player_was_participant'),
            'player_won_match' => $request->get('player_won_match'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
        ];

        // Remove empty filters
        $filters = array_filter($filters, function ($value) {
            return $value !== null && $value !== '';
        });

        $matchHistory = $this->userMatchHistoryService->getPaginatedMatchHistory($user, $perPage, $page, $filters);

        return response()->json($matchHistory);
    }

    public function show(Request $request, int $matchId)
    {
        // todo auth policies
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (empty($user->steam_id)) {
            return response()->json(['message' => 'Player not found'], 404);
        }

        $match = $this->userMatchHistoryService->getMatchById($user, $matchId);

        if (! $match) {
            return response()->json(['message' => 'Match not found'], 404);
        }

        // Add debug info to response
        $match['debug'] = [
            'user_id' => $user->id,
            'steam_id' => $user->steam_id,
            'has_player' => $user->player ? 'yes' : 'no',
            'match_id' => $matchId,
        ];

        return response()->json($match);
    }
}
