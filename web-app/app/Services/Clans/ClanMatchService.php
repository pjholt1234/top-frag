<?php

namespace App\Services\Clans;

use App\Models\Clan;
use App\Models\GameMatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ClanMatchService
{
    public function findMatchesWithMultipleClanMembers(Clan $clan): Collection
    {
        $clanMemberUserIds = $clan->members()->pluck('user_id');

        if ($clanMemberUserIds->count() < 2) {
            return collect();
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
            return collect();
        }

        // Find matches where 2+ clan members played together on the same team
        $matchIds = DB::table('match_players')
            ->whereIn('player_id', $clanMemberPlayerIds)
            ->select('match_id', 'team')
            ->groupBy('match_id', 'team')
            ->havingRaw('COUNT(DISTINCT player_id) >= 2')
            ->pluck('match_id')
            ->unique();

        return GameMatch::whereIn('id', $matchIds)->get();
    }

    public function checkAndAddMatch(Clan $clan, GameMatch $match): bool
    {
        if (! $match) {
            return false;
        }

        // Check if match is already in clan
        if (DB::table('clan_matches')
            ->where('clan_id', $clan->id)
            ->where('match_id', $match->id)
            ->exists()) {
            return false;
        }

        // Get player IDs for clan members
        $clanMemberUserIds = $clan->members()->pluck('user_id');

        if ($clanMemberUserIds->count() < 2) {
            return false;
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
            return false;
        }

        // Check if 2+ clan members played in this match on the same team
        $clanMembersInMatch = DB::table('match_players')
            ->where('match_id', $match->id)
            ->whereIn('player_id', $clanMemberPlayerIds)
            ->select('team')
            ->groupBy('team')
            ->havingRaw('COUNT(DISTINCT player_id) >= 2')
            ->exists();

        if ($clanMembersInMatch) {
            DB::table('clan_matches')->insert([
                'clan_id' => $clan->id,
                'match_id' => $match->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        }

        return false;
    }
}
