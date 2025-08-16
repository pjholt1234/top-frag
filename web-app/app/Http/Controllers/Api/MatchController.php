<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\UserMatchHistoryService;
use Illuminate\Http\Request;

class MatchController extends Controller
{
    public function __construct(private readonly UserMatchHistoryService $userMatchHistoryService) {}

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

        $matchHistory = $this->userMatchHistoryService->getPaginatedMatchHistory($user, $perPage, $page);

        return response()->json($matchHistory);
    }
}
