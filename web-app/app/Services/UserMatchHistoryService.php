<?php

namespace App\Services;

use App\Models\GameMatch;
use App\Models\Player;
use App\Models\User;
use Illuminate\Support\Collection;

class UserMatchHistoryService
{
    private ?Player $player;

    private ?User $user;

    public function setUser(User $user)
    {
        $this->user = $user;
        $this->player = $user->player;
    }

    public function aggregateMatchData(User $user): array
    {
        $this->setUser($user);

        if (! $this->player) {
            return [];
        }

        return $this->user->matches()
            ->map(function (GameMatch $match) {
                return [
                    'match_details' => $this->getMatchDetails($match),
                    'player_stats' => $this->getPlayerStats($match),
                ];
            })->toArray();
    }

    private function getMatchDetails(GameMatch $match): array
    {
        return [
            'match_id' => $match->id,
            'map' => $match->map,
            'winning_team_score' => $match->winning_team_score,
            'losing_team_score' => $match->losing_team_score,
            'winning_team_name' => $match->winning_team,
            'player_won_match' => $this->player->playerWonMatch($match),
            'match_type' => $match->match_type,
            'match_date' => $match->created_at,
            'player_was_participant' => true,
        ];
    }

    private function getPlayerStats(GameMatch $match)
    {
        return $match->players->map(function (Player $player) use ($match) {
            $allPlayerGunfightEvents = $this->getAllPlayerGunfightEvents($match, $player);

            $playerKillEvents = $allPlayerGunfightEvents->where('victor_steam_id', $player->steam_id);
            $playerDeathEvents = $allPlayerGunfightEvents->where('victor_steam_id', '!=', $player->steam_id);

            $playerKills = $playerKillEvents->count();
            $playerDeaths = $playerDeathEvents->count();

            $playerFirstKills = $playerKillEvents->where('is_first_kill', true)->count();
            $playerFirstDeaths = $playerDeathEvents->where('is_first_kill', true)->count();

            $openingKills = $playerFirstKills - $playerFirstDeaths;

            return [
                'player_kills' => $playerKills,
                'player_deaths' => $playerDeaths,
                'player_first_kill_differential' => $openingKills,
                'player_kill_death_ratio' => $this->calculateKillDeathRatio($playerKills, $playerDeaths),
                'player_adr' => $this->calculatePlayerAverageDamagePerRound($match, $player),
                'team' => $match->players->where('steam_id', $player->steam_id)->first()->pivot->team,
                'player_name' => $player->name,
            ];
        })->toArray();
    }

    public function getAllPlayerGunfightEvents(GameMatch $match, Player $player): Collection
    {
        return $match
            ->gunfightEvents()
            ->where(function ($query) use ($player) {
                $query->where('player_1_steam_id', $player->steam_id)
                    ->orWhere('player_2_steam_id', $player->steam_id);
            })
            ->get();
    }

    private function calculateKillDeathRatio(int $kills, int $deaths): float
    {
        if ($deaths === 0) {
            return 0.0;
        }

        return round($kills / $deaths, 2);
    }

    private function calculatePlayerAverageDamagePerRound(GameMatch $match, Player $player)
    {
        $totalDamage = $match
            ->damageEvents()
            ->where('attacker_steam_id', $player->steam_id)
            ->sum('health_damage');

        return round($totalDamage / $match->total_rounds, 2);
    }
}
