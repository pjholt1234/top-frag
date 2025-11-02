<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UtilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UtilityController extends Controller
{
    public function __construct(
        private readonly UtilityService $utilityService
    ) {}

    /**
     * Get utility stats data
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $this->parseFilters($request);
        $data = $this->utilityService->getUtilityStats($request->user(), $filters);

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
