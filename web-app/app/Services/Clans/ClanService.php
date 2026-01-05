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

            ClanMember::create([
                'clan_id' => $clan->id,
                'user_id' => $owner->id,
            ]);

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

        $this->findMatchesForClan($clan);

        return $clan;
    }

    /**
     * Add a user to a clan directly (used for Discord auto-add)
     */
    public function addMember(Clan $clan, User $user): void
    {
        if ($clan->isMember($user)) {
            return;
        }

        ClanMember::create([
            'clan_id' => $clan->id,
            'user_id' => $user->id,
        ]);

        $this->findMatchesForClan($clan);
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

        $matches = DB::table('match_players')
            ->whereIn('player_id', $clanMemberPlayerIds)
            ->select('match_id', 'team')
            ->groupBy('match_id', 'team')
            ->havingRaw('COUNT(DISTINCT player_id) >= 2')
            ->pluck('match_id')
            ->unique();

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
            $clan->delete();
        });
    }

    public function transferOwnership(Clan $clan, User $newOwner): void
    {
        DB::transaction(function () use ($clan, $newOwner) {
            if (! $clan->isMember($newOwner)) {
                throw new \Exception('New owner must be a member of the clan');
            }

            if ($clan->isOwner($newOwner)) {
                throw new \Exception('User is already the owner of this clan');
            }

            $clan->owned_by = $newOwner->id;
            $clan->save();
        });
    }

    /**
     * Create a new clan from Discord guild installation
     */
    public function createFromDiscordGuild(User $owner, string $guildId, string $guildName, ?string $tag = null): Clan
    {
        return DB::transaction(function () use ($owner, $guildId, $guildName, $tag) {
            $clan = Clan::create([
                'owned_by' => $owner->id,
                'invite_link' => (string) Str::uuid(),
                'name' => $guildName,
                'tag' => $tag,
                'discord_guild_id' => $guildId,
            ]);

            ClanMember::create([
                'clan_id' => $clan->id,
                'user_id' => $owner->id,
            ]);

            $this->findMatchesForClan($clan);

            return $clan;
        });
    }

    /**
     * Link an existing clan to a Discord guild
     */
    public function linkToDiscordGuild(Clan $clan, string $guildId, User $installer): Clan
    {
        return DB::transaction(function () use ($clan, $guildId, $installer) {
            if ($clan->discord_guild_id !== null) {
                throw new \Exception('This clan is already linked to a Discord server');
            }

            if (! $clan->isOwner($installer)) {
                throw new \Exception('You must be the owner of the clan to link it to Discord');
            }

            $clan->discord_guild_id = $guildId;
            $clan->save();

            return $clan;
        });
    }

    /**
     * Unlink clan from Discord server
     */
    public function unlinkFromDiscord(Clan $clan, User $user): void
    {
        DB::transaction(function () use ($clan, $user) {
            if (! $clan->isOwner($user)) {
                throw new \Exception('Only the clan owner can unlink from Discord.');
            }

            if (! $clan->isLinkedToDiscord()) {
                throw new \Exception('This clan is not linked to a Discord server.');
            }

            $clan->discord_guild_id = null;
            $clan->save();
        });
    }
}
