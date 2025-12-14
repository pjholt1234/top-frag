<?php

namespace App\Services\Clans;

use App\Models\Clan;
use App\Models\ClanMember;
use App\Models\GameMatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClanService
{
    public function create(User $owner, array $data): Clan
    {
        return DB::transaction(function () use ($owner, $data) {
            $clan = Clan::create([
                'owned_by' => $owner->id,
                'invite_link' => (string) Str::uuid(),
                'name' => $data['name'],
                'tag' => $data['tag'] ?? null,
            ]);

            // Add owner as first member
            ClanMember::create([
                'clan_id' => $clan->id,
                'user_id' => $owner->id,
            ]);

            // Find initial matches for the clan
            $this->findMatchesForClan($clan);

            return $clan;
        });
    }

    public function join(User $user, string $inviteLink): Clan
    {
        $clan = Clan::where('invite_link', $inviteLink)->firstOrFail();

        if ($clan->isMember($user)) {
            throw new \Exception('User is already a member of this clan');
        }

        ClanMember::create([
            'clan_id' => $clan->id,
            'user_id' => $user->id,
        ]);

        // Check for new matches with the new member
        $this->findMatchesForClan($clan);

        return $clan;
    }

    public function leave(User $user, Clan $clan): void
    {
        if ($clan->isOwner($user)) {
            throw new \Exception('Clan owner cannot leave the clan. Transfer ownership or delete the clan instead.');
        }

        $deleted = ClanMember::where('clan_id', $clan->id)
            ->where('user_id', $user->id)
            ->delete();

        if ($deleted === 0) {
            throw new \Exception('User is not a member of this clan');
        }
    }

    public function updateInviteLink(Clan $clan): string
    {
        return $clan->generateInviteLink();
    }

    public function findMatchesForClan(Clan $clan): void
    {
        $clanMemberUserIds = $clan->members()->pluck('user_id');

        if ($clanMemberUserIds->count() < 2) {
            return;
        }

        // Get player IDs for clan members
        $clanMemberPlayerIds = DB::table('players')
            ->whereIn('steam_id', function ($query) use ($clanMemberUserIds) {
                $query->select('steam_id')
                    ->from('users')
                    ->whereIn('id', $clanMemberUserIds)
                    ->whereNotNull('steam_id');
            })
            ->pluck('id');

        if ($clanMemberPlayerIds->isEmpty()) {
            return;
        }

        // Find matches where 2+ clan members played together on the same team
        $matches = DB::table('match_players')
            ->whereIn('player_id', $clanMemberPlayerIds)
            ->select('match_id', 'team')
            ->groupBy('match_id', 'team')
            ->havingRaw('COUNT(DISTINCT player_id) >= 2')
            ->pluck('match_id')
            ->unique();

        // Add matches to clan_matches if they don't already exist
        foreach ($matches as $matchId) {
            $this->addMatchToClan($clan, GameMatch::find($matchId));
        }
    }

    public function addMatchToClan(Clan $clan, GameMatch $match): void
    {
        if (! $match) {
            return;
        }

        DB::table('clan_matches')
            ->insertOrIgnore([
                'clan_id' => $clan->id,
                'match_id' => $match->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function delete(Clan $clan): void
    {
        DB::transaction(function () use ($clan) {
            // Delete clan - cascading deletes will handle:
            // - clan_members (via foreign key cascade)
            // - clan_matches (via foreign key cascade)
            // - clan_leaderboards (via foreign key cascade)
            $clan->delete();
        });
    }

    public function transferOwnership(Clan $clan, User $newOwner): void
    {
        DB::transaction(function () use ($clan, $newOwner) {
            // Verify new owner is a member of the clan
            if (! $clan->isMember($newOwner)) {
                throw new \Exception('New owner must be a member of the clan');
            }

            // Verify new owner is not already the owner
            if ($clan->isOwner($newOwner)) {
                throw new \Exception('User is already the owner of this clan');
            }

            // Transfer ownership
            $clan->owned_by = $newOwner->id;
            $clan->save();
        });
    }
}
