<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Clan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClanMatchController extends Controller
{
    public function index(Clan $clan, Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 10);
        $page = $request->get('page', 1);

        $perPage = max(1, min(50, (int) $perPage));
        $page = max(1, (int) $page);

        $matches = $clan->matches()
            ->with(['players', 'matchPlayers'])
            ->orderBy('match_start_time', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $matches->items(),
            'pagination' => [
                'current_page' => $matches->currentPage(),
                'per_page' => $matches->perPage(),
                'total' => $matches->total(),
                'last_page' => $matches->lastPage(),
            ],
        ]);
    }
}
