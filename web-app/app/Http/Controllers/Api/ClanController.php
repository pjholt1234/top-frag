<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\JoinClanRequest;
use App\Http\Requests\StoreClanRequest;
use App\Http\Requests\UpdateClanRequest;
use App\Models\Clan;
use App\Models\User;
use App\Services\Clans\ClanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ClanController extends Controller
{
    public function __construct(
        private readonly ClanService $clanService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $clans = Clan::whereHas('members', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->with(['owner', 'members.user'])->get();

        return response()->json([
            'data' => $clans,
        ]);
    }

    public function store(StoreClanRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        try {
            $clan = $this->clanService->create($user, $request->only(['name', 'tag']));

            return response()->json([
                'message' => 'Clan created successfully',
                'data' => $clan->load(['owner', 'members.user']),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating clan', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Failed to create clan',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(Clan $clan): JsonResponse
    {
        return response()->json([
            'data' => $clan->load(['owner', 'members.user', 'matches']),
        ]);
    }

    public function update(UpdateClanRequest $request, Clan $clan): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $clan->refresh();

        if (! $clan->isOwner($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $clan->update($request->only(['name', 'tag']));

        return response()->json([
            'message' => 'Clan updated successfully',
            'data' => $clan->load(['owner', 'members.user']),
        ]);
    }

    public function destroy(Clan $clan): JsonResponse
    {
        $user = request()->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $clan->refresh();

        if (! $clan->isOwner($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $this->clanService->delete($clan);

            return response()->json([
                'message' => 'Clan deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting clan', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'clan_id' => $clan->id,
            ]);

            return response()->json([
                'message' => 'Failed to delete clan',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function regenerateInviteLink(Clan $clan): JsonResponse
    {
        $user = request()->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $clan->refresh();

        if (! $clan->isOwner($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $inviteLink = $this->clanService->updateInviteLink($clan);

        return response()->json([
            'message' => 'Invite link regenerated successfully',
            'invite_link' => $inviteLink,
        ]);
    }

    public function join(JoinClanRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        try {
            $clan = $this->clanService->join($user, $request->invite_link);

            return response()->json([
                'message' => 'Joined clan successfully',
                'data' => $clan->load(['owner', 'members.user']),
            ]);
        } catch (\Exception $e) {
            Log::error('Error joining clan', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'invite_link' => $request->invite_link,
            ]);

            return response()->json([
                'message' => $e->getMessage(),
                'error' => 'join_failed',
            ], 400);
        }
    }

    public function leave(Clan $clan): JsonResponse
    {
        $user = request()->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        try {
            $this->clanService->leave($user, $clan);

            return response()->json([
                'message' => 'Left clan successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Error leaving clan', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'clan_id' => $clan->id,
            ]);

            return response()->json([
                'message' => $e->getMessage(),
                'error' => 'leave_failed',
            ], 400);
        }
    }

    public function transferOwnership(Clan $clan, User $user): JsonResponse
    {
        $currentUser = request()->user();

        if (! $currentUser) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $clan->refresh();

        if (! $clan->isOwner($currentUser)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $this->clanService->transferOwnership($clan, $user);

            return response()->json([
                'message' => 'Ownership transferred successfully',
                'data' => $clan->load(['owner', 'members.user']),
            ]);
        } catch (\Exception $e) {
            Log::error('Error transferring ownership', [
                'error' => $e->getMessage(),
                'user_id' => $currentUser->id,
                'clan_id' => $clan->id,
                'new_owner_id' => $user->id,
            ]);

            return response()->json([
                'message' => $e->getMessage(),
                'error' => 'transfer_failed',
            ], 400);
        }
    }
}
