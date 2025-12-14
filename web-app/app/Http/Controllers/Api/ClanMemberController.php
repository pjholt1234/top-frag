<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClanMemberResource;
use App\Models\Clan;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class ClanMemberController extends Controller
{
    public function index(Clan $clan): JsonResponse
    {
        $members = $clan->members()->with(['user.player.playerRanks' => function ($query) {
            $query->whereIn('rank_type', ['faceit', 'premier'])
                ->orderBy('created_at', 'desc');
        }])->get();

        return response()->json([
            'data' => $members->map(function ($member) use ($clan) {
                return (new ClanMemberResource($member, $clan))->toArray(request());
            })->values(),
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
