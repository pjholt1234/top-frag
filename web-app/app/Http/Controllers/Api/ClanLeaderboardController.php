<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeaderboardType;
use App\Http\Controllers\Controller;
use App\Http\Resources\ClanLeaderboardResource;
use App\Models\Clan;
use App\Services\Clans\ClanLeaderboardService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClanLeaderboardController extends Controller
{
    public function __construct(
        private readonly ClanLeaderboardService $leaderboardService
    ) {}

    public function index(Clan $clan, Request $request): JsonResponse
    {
        $type = $request->get('type');
        $period = $request->get('period', 'week'); // week or month
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $now = Carbon::now();

        if ($startDate && $endDate) {
            $start = Carbon::parse($startDate);
            $end = Carbon::parse($endDate);
        } elseif ($period === 'month') {
            $start = $now->copy()->subDays(30)->startOfDay();
            $end = $now->copy()->endOfDay();
        } else {
            $start = $now->copy()->subDays(7)->startOfDay();
            $end = $now->copy()->endOfDay();
        }

        if ($type) {
            // Get specific leaderboard type
            $leaderboard = $this->leaderboardService->getLeaderboard($clan, $type, $start, $end);

            return response()->json([
                'data' => ClanLeaderboardResource::collection($leaderboard),
                'type' => $type,
                'start_date' => $start->format('Y-m-d'),
                'end_date' => $end->format('Y-m-d'),
            ]);
        }

        // Get all leaderboard types
        $leaderboards = [];
        foreach (LeaderboardType::cases() as $leaderboardType) {
            $leaderboard = $this->leaderboardService->getLeaderboard(
                $clan,
                $leaderboardType->value,
                $start,
                $end
            );
            $leaderboards[$leaderboardType->value] = ClanLeaderboardResource::collection($leaderboard);
        }

        return response()->json([
            'data' => $leaderboards,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
        ]);
    }

    public function show(Clan $clan, string $type, Request $request): JsonResponse
    {
        $period = $request->get('period', 'week');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $now = Carbon::now();

        if ($startDate && $endDate) {
            $start = Carbon::parse($startDate);
            $end = Carbon::parse($endDate);
        } elseif ($period === 'month') {
            $start = $now->copy()->subDays(30)->startOfDay();
            $end = $now->copy()->endOfDay();
        } else {
            $start = $now->copy()->subDays(7)->startOfDay();
            $end = $now->copy()->endOfDay();
        }

        $leaderboard = $this->leaderboardService->getLeaderboard($clan, $type, $start, $end);

        return response()->json([
            'data' => ClanLeaderboardResource::collection($leaderboard),
            'type' => (string) $type,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
        ]);
    }
}
