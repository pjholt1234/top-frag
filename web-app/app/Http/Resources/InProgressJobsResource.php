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

            $data = [
                ...$data,
                'match_id' => $match->id,
                'map' => $match->map,
                'winning_team_score' => $match->winning_team_score,
                'losing_team_score' => $match->losing_team_score,
                'winning_team_name' => $match->winning_team,
                'player_won_match' => empty($player) ? false : $player->playerWonMatch($match),
                'match_type' => $match->match_type,
                'match_date' => $match->created_at,
                'player_was_participant' => true,
            ];
        }

        return $data;
    }
}
