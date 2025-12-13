<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Analytics\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService
    ) {}

    /**
     * Get player stats dashboard data
     */
    public function playerStats(Request $request): JsonResponse
    {
        $filters = $this->parseFilters($request);
        $data = $this->dashboardService->getPlayerStats($request->user(), $filters);

        return response()->json($data);
    }

    /**
     * Get aim stats dashboard data
     */
    public function aimStats(Request $request): JsonResponse
    {
        $filters = $this->parseFilters($request);
        $data = $this->dashboardService->getAimStats($request->user(), $filters);

        return response()->json($data);
    }

    /**
     * Get utility stats dashboard data
     */
    public function utilityStats(Request $request): JsonResponse
    {
        $filters = $this->parseFilters($request);
        $data = $this->dashboardService->getUtilityStats($request->user(), $filters);

        return response()->json($data);
    }

    /**
     * Get summary dashboard data
     */
    public function summary(Request $request): JsonResponse
    {
        $filters = $this->parseFilters($request);
        $data = $this->dashboardService->getSummary($request->user(), $filters);

        return response()->json($data);
    }

    /**
     * Get map stats dashboard data
     */
    public function mapStats(Request $request): JsonResponse
    {
        $filters = $this->parseFilters($request);
        $data = $this->dashboardService->getMapStats($request->user(), $filters);

        return response()->json($data);
    }

    /**
     * Get rank stats dashboard data
     */
    public function rankStats(Request $request): JsonResponse
    {
        $filters = $this->parseFilters($request);
        $data = $this->dashboardService->getRankStats($request->user(), $filters);

        return response()->json($data);
    }

    /**
     * Parse and validate filters from request
     */
    private function parseFilters(Request $request): array
    {
        return [
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'game_type' => $request->input('game_type'),
            'map' => $request->input('map'),
            'past_match_count' => (int) ($request->input('past_match_count') ?? 10),
        ];
    }
}
