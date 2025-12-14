<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Clan;
use App\Services\Matches\MatchDetailsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClanMatchController extends Controller
{
    public function __construct(
        private readonly MatchDetailsService $matchDetailsService,
    ) {}

    public function index(Clan $clan, Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => config('messaging.auth.unauthorised')], 403);
        }

        $perPage = $request->get('per_page', 10);
        $page = $request->get('page', 1);

        $perPage = max(1, min(50, (int) $perPage));
        $page = max(1, (int) $page);

        $matches = $clan->matches()
            ->with(['players', 'matchPlayers'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        // Transform matches to match the structure expected by the frontend
        $transformedMatches = collect($matches->items())->map(function ($match) use ($user) {
            return $this->matchDetailsService->getDetails($user, $match->id);
        })->toArray();

        $offset = ($page - 1) * $perPage;
        $total = $matches->total();

        return response()->json([
            'data' => $transformedMatches,
            'pagination' => [
                'current_page' => $matches->currentPage(),
                'per_page' => $matches->perPage(),
                'total' => $total,
                'last_page' => $matches->lastPage(),
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $total),
            ],
        ]);
    }
}
