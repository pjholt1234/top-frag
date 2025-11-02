<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PlayerCardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlayerCardController extends Controller
{
    public function __construct(
        private readonly PlayerCardService $playerCardService
    ) {}

    /**
     * Get player card data for a given steam ID
     */
    public function getPlayerCard(Request $request, string $steamId): JsonResponse
    {
        $data = $this->playerCardService->getPlayerCard($steamId);

        return response()->json($data);
    }
}
