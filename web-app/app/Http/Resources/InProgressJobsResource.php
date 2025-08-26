<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InProgressJobsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $match = $this->match;

        $data = [
            'processing_status' => $this->processing_status,
            'progress_percentage' => $this->progress_percentage,
            'current_step' => $this->current_step,
            'error_message' => $this->error_message,
        ];

        if (! empty($match)) {
            $player = $this->user->player;

            // Check if player participated in this match
            $playerTeam = $match->players->where('steam_id', $player?->steam_id)->first()?->pivot->team;
            $playerWasParticipant = $playerTeam !== null;
            $playerWonMatch = $playerWasParticipant && $match->winning_team === $playerTeam;

            $data = [
                ...$data,
                'match_id' => $match->id,
                'map' => $match->map,
                'winning_team_score' => $match->winning_team_score,
                'losing_team_score' => $match->losing_team_score,
                'winning_team' => $match->winning_team,
                'player_won_match' => $playerWonMatch,
                'player_was_participant' => $playerWasParticipant,
                'player_team' => $playerTeam,
                'match_type' => $match->match_type,
                'created_at' => $match->created_at,
            ];
        }

        return $data;
    }
}
