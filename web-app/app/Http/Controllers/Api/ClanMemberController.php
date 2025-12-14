<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Clan;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class ClanMemberController extends Controller
{
    public function index(Clan $clan): JsonResponse
    {
        return response()->json([
            'data' => $clan->members()->with('user')->get(),
        ]);
    }

    public function destroy(Clan $clan, User $user): JsonResponse
    {
        $currentUser = request()->user();

        if (! $currentUser) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $clan->refresh();

        if ((int) $clan->owned_by !== (int) $currentUser->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ((int) $clan->owned_by === (int) $user->id) {
            return response()->json([
                'message' => 'Cannot remove clan owner',
                'error' => 'cannot_remove_owner',
            ], 400);
        }

        $clan->members()->where('user_id', $user->id)->delete();

        return response()->json([
            'message' => 'Member removed successfully',
        ]);
    }
}
