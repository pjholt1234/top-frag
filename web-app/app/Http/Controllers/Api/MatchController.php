<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GameMatch;
use App\Services\Matches\MatchDetailsService;
use App\Services\Matches\PlayerStatsService;
use App\Services\Matches\UtilityAnalysisService;
use App\Services\Matches\GrenadeExplorerService;
use App\Services\Matches\HeadToHeadService;
use App\Services\MatchHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MatchController extends Controller
{
    public function __construct(
        private readonly MatchHistoryService $matchHistoryService,
        private readonly MatchDetailsService $matchDetailsService,
        private readonly PlayerStatsService $playerStatsService,
        private readonly UtilityAnalysisService $utilityAnalysisService,
        private readonly GrenadeExplorerService $grenadeExplorerService,
        private readonly HeadToHeadService $headToHeadService,
    ) {}

    public function index(Request $request)
    {
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

        $matchHistory = $this->matchHistoryService->getPaginatedMatchHistory($user, $perPage, $page, $filters);

        return response()->json($matchHistory);
    }

    public function utilityAnalysis(Request $request, int $matchId): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (empty($user->steam_id)) {
            return response()->json(['message' => 'Player not found'], 404);
        }

        $playerSteamId = $request->get('player_steam_id');
        $roundNumber = $request->get('round_number');

        if ($roundNumber && $roundNumber !== 'all') {
            $roundNumber = (int) $roundNumber;
        } else {
            $roundNumber = null;
        }

        $analysis = $this->utilityAnalysisService->getAnalysis(
            $user,
            $matchId,
            $playerSteamId,
            $roundNumber
        );

        // Only return 404 if the match itself doesn't exist or user doesn't have access
        // Empty analysis data (e.g., no grenade events for a specific round) is valid
        if (empty($analysis)) {
            return response()->json(['message' => 'Match not found'], 404);
        }

        return response()->json($analysis);
    }

    public function matchDetails(Request $request, int $matchId): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (empty($user->steam_id)) {
            return response()->json(['message' => 'Player not found'], 404);
        }

        $details = $this->matchDetailsService->getDetails($user, $matchId);

        if (empty($details)) {
            return response()->json(['message' => 'Match not found'], 404);
        }

        return response()->json($details);
    }

    public function playerStats(Request $request, int $matchId): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (empty($user->steam_id)) {
            return response()->json(['message' => 'Player not found'], 404);
        }

        $stats = $this->playerStatsService->getStats($user, $matchId);

        if (empty($stats)) {
            return response()->json(['message' => 'Match not found'], 404);
        }

        return response()->json($stats);
    }

    public function grenadeExplorer(Request $request, int $matchId)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (empty($user->steam_id)) {
            return response()->json(['message' => 'Player not found'], 404);
        }

        $filters = $request->only(['map', 'match_id', 'round_number', 'grenade_type', 'player_steam_id', 'player_side']);
        $explorer = $this->grenadeExplorerService->getExplorer($user, $filters, $matchId);

        if (empty($explorer)) {
            return response()->json(['message' => 'Match not found'], 404);
        }

        return response()->json($explorer);
    }

    public function grenadeExplorerFilterOptions(Request $request, int $matchId): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (empty($user->steam_id)) {
            return response()->json(['message' => 'Player not found'], 404);
        }

        $filters = $request->only(['map']);
        $filterOptions = $this->grenadeExplorerService->getFilterOptions($user, $filters, $matchId);

        if (empty($filterOptions)) {
            return response()->json(['message' => 'Match not found'], 404);
        }

        return response()->json($filterOptions);
    }

    public function headToHead(Request $request, int $matchId)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (empty($user->steam_id)) {
            return response()->json(['message' => 'Player not found'], 404);
        }

        $player1SteamId = $request->get('player1_steam_id');
        $player2SteamId = $request->get('player2_steam_id');

        $headToHead = $this->headToHeadService->getHeadToHead($user, $matchId, $player1SteamId, $player2SteamId);

        if (empty($headToHead)) {
            return response()->json(['message' => 'Match not found'], 404);
        }

        return response()->json($headToHead);
    }
}
