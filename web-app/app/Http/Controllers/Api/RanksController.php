<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RanksService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RanksController extends Controller
{
    public function __construct(
        private readonly RanksService $ranksService
    ) {}

    /**
     * Get rank stats data
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $this->parseFilters($request);
        $data = $this->ranksService->getRankStats($request->user(), $filters);

        return response()->json($data);
    }

    /**
     * Parse and validate filters from request
     * Note: Ranks don't use game_type or map filters
     */
    private function parseFilters(Request $request): array
    {
        return [
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'past_match_count' => (int) ($request->input('past_match_count') ?? 10),
        ];
    }
}
