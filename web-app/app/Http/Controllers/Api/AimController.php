<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AimService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AimController extends Controller
{
    public function __construct(
        private readonly AimService $aimService
    ) {}

    /**
     * Get aim stats data
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $this->parseFilters($request);
        $data = $this->aimService->getAimStats($request->user(), $filters);

        return response()->json($data);
    }

    /**
     * Get available weapons for filtered matches
     */
    public function weapons(Request $request): JsonResponse
    {
        $filters = $this->parseFilters($request);
        $weapons = $this->aimService->getAvailableWeapons($request->user(), $filters);

        return response()->json($weapons);
    }

    /**
     * Get hit distribution data
     */
    public function hitDistribution(Request $request): JsonResponse
    {
        $filters = $this->parseFilters($request);
        $weaponName = $request->input('weapon_name');

        $data = $this->aimService->getHitDistribution(
            $request->user(),
            $filters,
            $weaponName
        );

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
