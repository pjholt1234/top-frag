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
        $members = ClanMember::where('clan_id', $clan->id)
            ->with('user')
            ->get();

        return response()->json([
            'data' => $members,
        ]);
    }

    public function destroy(Clan $clan, User $user): JsonResponse
    {
        $currentUser = request()->user();

        if (! $currentUser) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Refresh clan to ensure we have the latest data
        $clan->refresh();

        if (! $clan->isOwner($currentUser)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($clan->isOwner($user)) {
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
