<?php

namespace App\Http\Resources;

use App\Models\ClanLeaderboard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClanLeaderboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ClanLeaderboard $leaderboard */
        $leaderboard = $this->resource;
        $user = $leaderboard->user;

        if (! $user) {
            return [];
        }

        // Flatten the structure to match frontend expectations
        return [
            'position' => $leaderboard->position,
            'user_id' => $user->id,
            'user_name' => $user->name ?? $user->steam_persona_name ?? 'Unknown',
            'user_avatar' => $user->steam_avatar ?? null,
            'value' => (float) $leaderboard->value,
        ];
    }
}
