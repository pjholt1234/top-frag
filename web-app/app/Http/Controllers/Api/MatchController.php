<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\UserMatchHistoryService;

class MatchController extends Controller
{
    public function index(Request $request)
    {
        //todo auth policies
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (empty($user->steam_id)) {
            return response()->json(['message' => 'Player not found'], 404);
        }

        $userMatchHistoryService = new UserMatchHistoryService($user);
        $matchHistory = $userMatchHistoryService->aggregateMatchData();

        return response()->json($matchHistory);
    }
}
