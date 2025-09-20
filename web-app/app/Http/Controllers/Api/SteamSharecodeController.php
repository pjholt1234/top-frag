<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSteamSharecodeRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SteamSharecodeController extends Controller
{
    /**
     * Store or update user's Steam sharecode
     */
    public function store(StoreSteamSharecodeRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'User not authenticated',
            ], 401);
        }

        $user->update([
            'steam_sharecode' => $request->steam_sharecode,
            'steam_sharecode_added_at' => now(),
        ]);

        return response()->json([
            'message' => 'Steam sharecode saved successfully',
            'user' => $user->fresh(),
        ]);
    }

    /**
     * Check if user has a Steam sharecode configured
     */
    public function hasSharecode(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'User not authenticated',
            ], 401);
        }

        return response()->json([
            'has_sharecode' => $user->hasSteamSharecode(),
            'steam_sharecode_added_at' => $user->steam_sharecode_added_at,
        ]);
    }

    /**
     * Remove user's Steam sharecode
     */
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'User not authenticated',
            ], 401);
        }

        if (! $user->hasSteamSharecode()) {
            return response()->json([
                'message' => 'No Steam sharecode configured',
                'error' => 'no_sharecode',
            ], 400);
        }

        $user->update([
            'steam_sharecode' => null,
            'steam_sharecode_added_at' => null,
            'steam_match_processing_enabled' => false,
        ]);

        return response()->json([
            'message' => 'Steam sharecode removed successfully',
            'user' => $user->fresh(),
        ]);
    }

    /**
     * Toggle Steam match processing enabled status
     */
    public function toggleProcessing(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'User not authenticated',
            ], 401);
        }

        if (! $user->hasSteamSharecode()) {
            return response()->json([
                'message' => 'Steam sharecode must be configured before enabling match processing',
                'error' => 'no_sharecode',
            ], 400);
        }

        $user->update([
            'steam_match_processing_enabled' => ! $user->steam_match_processing_enabled,
        ]);

        return response()->json([
            'message' => $user->steam_match_processing_enabled
                ? 'Steam match processing enabled'
                : 'Steam match processing disabled',
            'steam_match_processing_enabled' => $user->steam_match_processing_enabled,
            'user' => $user->fresh(),
        ]);
    }
}
