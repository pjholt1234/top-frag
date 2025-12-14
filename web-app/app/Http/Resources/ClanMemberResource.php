<?php

namespace App\Http\Resources;

use App\Models\Clan;
use App\Models\ClanMember;
use App\Models\Player;
use App\Models\PlayerRank;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClanMemberResource extends JsonResource
{
    protected Clan $clan;

    public function __construct($resource, Clan $clan)
    {
        parent::__construct($resource);
        $this->clan = $clan;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ClanMember $clanMember */
        $clanMember = $this->resource;
        $user = $clanMember->user;

        if (! $user) {
            return [];
        }

        // Get player and latest ranks
        $player = $user->player;
        $faceitRank = null;
        $premierRank = null;

        if ($player && $player->relationLoaded('playerRanks')) {
            // Use eager loaded ranks
            $faceitRank = $player->playerRanks
                ->where('rank_type', 'faceit')
                ->sortByDesc('created_at')
                ->first();

            $premierRank = $player->playerRanks
                ->where('rank_type', 'premier')
                ->sortByDesc('created_at')
                ->first();
        } elseif ($player) {
            // Fallback to query if not eager loaded
            $faceitRank = PlayerRank::where('player_id', $player->id)
                ->where('rank_type', 'faceit')
                ->orderBy('created_at', 'desc')
                ->first();

            $premierRank = PlayerRank::where('player_id', $player->id)
                ->where('rank_type', 'premier')
                ->orderBy('created_at', 'desc')
                ->first();
        }

        // Flatten the structure
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'steam_id' => $user->steam_id,
            'steam_persona_name' => $user->steam_persona_name,
            'steam_avatar' => $user->steam_avatar,
            'steam_avatar_medium' => $user->steam_avatar_medium,
            'is_owner' => (int) $this->clan->owned_by === (int) $user->id,
            'faceit_rank' => $faceitRank ? [
                'rank' => $faceitRank->rank,
                'rank_value' => $faceitRank->rank_value,
            ] : null,
            'premier_rank' => $premierRank ? [
                'rank' => $premierRank->rank,
                'rank_value' => $premierRank->rank_value,
            ] : null,
        ];
    }
}
