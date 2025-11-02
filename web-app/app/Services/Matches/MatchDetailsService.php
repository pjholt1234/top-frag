<?php

namespace App\Services\Matches;

use App\Models\Achievement;
use App\Models\GameMatch;
use App\Models\PlayerMatchEvent;
use App\Models\User;
use App\Services\MatchCacheManager;

class MatchDetailsService
{
    use MatchAccessTrait;

    public function getDetails(User $user, int $matchId): array
    {
        return MatchCacheManager::remember('match-details', $matchId, function () use ($user, $matchId) {
            return $this->buildMatchDetails($user, $matchId);
        });
    }

    private function getMatchDetails(User $user, GameMatch $match): array
    {
        $playerTeam = null;
        $playerWasParticipant = false;
        $playerWonMatch = true;

        if (! empty($user->player->steam_id)) {
            $playerParticipant = $match->players->where('steam_id', $user->player->steam_id)->first();
            if ($playerParticipant) {
                $playerTeam = $playerParticipant->pivot->team;
                $playerWasParticipant = true;
                $playerWonMatch = $match->winning_team === $playerTeam;
            }
        }

        return [
            'id' => $match->id,
            'map' => $match->map,
            'winning_team_score' => $match->winning_team_score,
            'losing_team_score' => $match->losing_team_score,
            'winning_team' => $match->winning_team,
            'player_won_match' => $playerWonMatch,
            'player_was_participant' => $playerWasParticipant,
            'player_team' => $playerTeam,
            'match_type' => $match->match_type,
            'game_mode' => $match->game_mode,
            'created_at' => $match->created_at,
        ];
    }

    private function buildMatchDetails(User $user, int $matchId): array
    {
        if (! $this->hasUserAccessToMatch($user, $matchId)) {
            return [];
        }

        $match = GameMatch::with(['players'])->find($matchId);

        if (! $match) {
            return [];
        }

        return [
            'id' => $match->id,
            'created_at' => $match->created_at,
            'is_completed' => true,
            'match_details' => $this->getMatchDetails($user, $match),
            'player_stats' => $this->getScoreBoardStats($match),
            'achievements' => $this->getUserAchievements($user, $match->id),
            'processing_status' => null,
            'progress_percentage' => null,
            'current_step' => null,
            'error_message' => null,
        ];
    }

    private function getScoreBoardStats(GameMatch $match): array
    {
        return $match->playerMatchEvents->map(function (PlayerMatchEvent $playerMatchEvent) use ($match) {
            $playerSteamId = $playerMatchEvent->player_steam_id;

            $openingKills = $playerMatchEvent['first_kills'] - $playerMatchEvent['first_deaths'];

            return [
                'rank_value' => $playerMatchEvent['rank_value'],
                'player_kills' => $playerMatchEvent['kills'],
                'player_deaths' => $playerMatchEvent['deaths'],
                'player_first_kill_differential' => $openingKills,
                'player_kill_death_ratio' => $this->calculateKillDeathRatio(
                    $playerMatchEvent['kills'],
                    $playerMatchEvent['deaths']
                ),
                'player_adr' => round($playerMatchEvent['adr']) ?? 0,
                'team' => $match->players->where('steam_id', $playerSteamId)->first()->pivot->team,
                'player_name' => $playerMatchEvent->player->name,
            ];
        })->toArray();
    }

    private function calculateKillDeathRatio(int $kills, int $deaths): float
    {
        if ($deaths === 0) {
            return 0.0;
        }

        return round($kills / $deaths, 2);
    }

    private function getUserAchievements(User $user, int $matchId): array
    {
        if (! $user->player) {
            return [];
        }

        $achievements = Achievement::where('match_id', $matchId)
            ->where('player_id', $user->player->id)
            ->get();

        return $achievements->map(function (Achievement $achievement) {
            return [
                'award_name' => $achievement->award_name->value,
            ];
        })->toArray();
    }
}
