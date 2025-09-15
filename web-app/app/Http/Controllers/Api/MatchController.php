<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\PlayerNotFound;
use App\Http\Controllers\Controller;
use App\Http\Requests\GrenadeExplorerFilterOptionsRequest;
use App\Http\Requests\GrenadeExplorerRequest;
use App\Http\Requests\HeadToHeadRequest;
use App\Http\Requests\IndexMatchHistoryRequest;
use App\Http\Requests\PlayerStatsRequest;
use App\Http\Requests\UtilityAnalysisRequest;
use App\Services\Matches\GrenadeExplorerService;
use App\Services\Matches\HeadToHeadService;
use App\Services\Matches\MatchDetailsService;
use App\Services\Matches\PlayerStatsService;
use App\Services\Matches\TopRolePlayerService;
use App\Services\Matches\UtilityAnalysisService;
use App\Services\MatchHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MatchController extends Controller
{
    public function __construct(
        private readonly MatchHistoryService $matchHistoryService,
        private readonly MatchDetailsService $matchDetailsService,
        private readonly PlayerStatsService $playerStatsService,
        private readonly TopRolePlayerService $topRolePlayerService,
        private readonly UtilityAnalysisService $utilityAnalysisService,
        private readonly GrenadeExplorerService $grenadeExplorerService,
        private readonly HeadToHeadService $headToHeadService,
    ) {}

    public function index(IndexMatchHistoryRequest $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => config('messaging.auth.unauthorised')], 403);
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

        try {
            $matchHistory = $this->matchHistoryService->getPaginatedMatchHistory($user, $perPage, $page, $filters);
        } catch (PlayerNotFound $e) {
            Log::warning($e->getMessage());

            return response()->json([
                'data' => [],
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => 0,
                    'last_page' => 1,
                ],
                'message' => $e->getMessage(),
            ], 404);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'data' => [],
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => 0,
                    'last_page' => 1,
                ],
                'message' => $e->getMessage(),
            ], 500);
        }

        return response()->json($matchHistory);
    }

    public function utilityAnalysis(UtilityAnalysisRequest $request, int $matchId): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => config('messaging.auth.unauthorised')], 403);
        }

        $playerSteamId = $request->get('player_steam_id');
        $roundNumber = $request->get('round_number');

        if ($roundNumber && $roundNumber !== 'all') {
            $roundNumber = (int) $roundNumber;
        } else {
            $roundNumber = null;
        }

        try {
            $analysis = $this->utilityAnalysisService->getAnalysis(
                $user,
                $matchId,
                $playerSteamId,
                $roundNumber
            );
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json(['message' => config('messaging.generic.critical-error')], 500);
        }

        // Only return 404 if the match itself doesn't exist or user doesn't have access
        // Empty analysis data (e.g., no grenade events for a specific round) is valid
        if (empty($analysis)) {
            // If user doesn't have steam_id, return empty data instead of 404
            if (! $user->steam_id) {
                return response()->json([
                    'utility_usage' => [],
                    'grenade_effectiveness' => [],
                    'grenade_timing' => [],
                    'overall_stats' => [
                        'overall_grenade_rating' => 0,
                        'flash_stats' => [
                            'enemy_avg_duration' => 0,
                            'friendly_avg_duration' => 0,
                            'enemy_avg_blinded' => 0,
                            'friendly_avg_blinded' => 0,
                        ],
                        'he_stats' => ['avg_damage' => 0],
                    ],
                    'players' => [],
                    'rounds' => [],
                    'current_user_steam_id' => null,
                ]);
            }

            return response()->json(['message' => config('messaging.matches.not-found-error')], 404);
        }

        return response()->json($analysis);
    }

    public function matchDetails(Request $request, int $matchId): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => config('messaging.auth.unauthorised')], 403);
        }

        try {
            $details = $this->matchDetailsService->getDetails($user, $matchId);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json(['message' => config('messaging.generic.critical-error')], 500);
        }

        if (empty($details)) {
            return response()->json(['message' => config('messaging.matches.not-found-error')], 404);
        }

        return response()->json($details);
    }

    public function playerStats(PlayerStatsRequest $request, int $matchId): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => config('messaging.auth.unauthorised')], 403);
        }

        $filters = $request->only(['player_steam_id']);
        try {
            $stats = $this->playerStatsService->get($user, $filters, $matchId);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json(['message' => config('messaging.generic.critical-error')], 500);
        }

        if (empty($stats)) {
            return response()->json(['message' => config('messaging.matches.not-found-error')], 404);
        }

        return response()->json($stats);
    }

    public function grenadeExplorer(GrenadeExplorerRequest $request, int $matchId): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => config('messaging.auth.unauthorised')], 403);
        }

        $filters = $request->only(['map', 'match_id', 'round_number', 'grenade_type', 'player_steam_id', 'player_side']);

        try {
            $explorer = $this->grenadeExplorerService->getExplorer($filters, $matchId);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json(['message' => config('messaging.generic.critical-error')], 500);
        }

        if (empty($explorer)) {
            return response()->json(['message' => config('messaging.matches.not-found-error')], 404);
        }

        return response()->json($explorer);
    }

    public function grenadeExplorerFilterOptions(GrenadeExplorerFilterOptionsRequest $request, int $matchId): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => config('messaging.auth.unauthorised')], 403);
        }

        $filters = $request->only(['map']);

        try {
            $filterOptions = $this->grenadeExplorerService->getFilterOptions($filters, $matchId);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json(['message' => config('messaging.generic.critical-error')], 500);
        }

        if (empty($filterOptions)) {
            return response()->json(['message' => config('messaging.matches.not-found-error')], 404);
        }

        return response()->json($filterOptions);
    }

    public function headToHead(HeadToHeadRequest $request, int $matchId): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => config('messaging.auth.unauthorised')], 403);
        }

        $player1SteamId = $request->get('player1_steam_id');
        $player2SteamId = $request->get('player2_steam_id');

        try {
            $headToHead = $this->headToHeadService->getHeadToHead($user, $matchId, $player1SteamId, $player2SteamId);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json(['message' => config('messaging.generic.critical-error')], 500);
        }

        if (empty($headToHead)) {
            return response()->json(['message' => config('messaging.matches.not-found-error')], 404);
        }

        return response()->json($headToHead);
    }

    public function topRolePlayers(Request $request, int $matchId): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => config('messaging.auth.unauthorised')], 403);
        }

        try {
            $topRolePlayers = $this->topRolePlayerService->get($matchId);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json(['message' => config('messaging.generic.critical-error')], 500);
        }

        if (empty($topRolePlayers)) {
            return response()->json(['message' => config('messaging.matches.not-found-error')], 404);
        }

        return response()->json($topRolePlayers);
    }
}
