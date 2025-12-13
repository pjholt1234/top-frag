<?php

namespace App\Services\Matches;

use App\Models\PlayerMatchEvent;
use App\Services\Infrastructure\MatchCacheManager;

class PlayerComplexionService
{
    private array $config;

    public function __construct()
    {
        $this->config = config('player-complexion');
    }

    public function get(string $playerSteamId, int $matchId): array
    {
        $cacheKey = $this->getCacheKey($playerSteamId);

        return MatchCacheManager::remember($cacheKey, $matchId, function () use ($playerSteamId, $matchId) {
            return $this->buildComplexion($playerSteamId, $matchId);
        });
    }

    private function getCacheKey(string $playerSteamId): string
    {
        return "player-complexion_{$playerSteamId}";
    }

    private function buildComplexion(string $playerSteamId, int $matchId): array
    {
        $playerMatchEvent = PlayerMatchEvent::query()
            ->where('match_id', $matchId)
            ->where('player_steam_id', $playerSteamId)
            ->first();

        if (! $playerMatchEvent) {
            return [];
        }

        return [
            'opener' => $this->playerOpenerScore($playerMatchEvent),
            'closer' => $this->playerCloserScore($playerMatchEvent),
            'support' => $this->playerSupportScore($playerMatchEvent),
            'fragger' => $this->playerFraggerScore($playerMatchEvent),
        ];
    }

    private function playerOpenerScore(PlayerMatchEvent $playerMatchEvent): int
    {
        $openerConfig = $this->config['opener'];
        $scores = [];

        // Average round time of death
        $scores[] = [
            'score' => $this->normaliseScore(
                $playerMatchEvent->average_round_time_of_death,
                $openerConfig['average_round_time_of_death']['score'],
                $openerConfig['average_round_time_of_death']['higher_better']
            ),
            'weight' => $openerConfig['average_round_time_of_death']['weight'],
        ];

        // Average time to contact
        $scores[] = [
            'score' => $this->normaliseScore(
                $playerMatchEvent->average_time_to_contact,
                $openerConfig['average_time_to_contact']['score'],
                $openerConfig['average_time_to_contact']['higher_better']
            ),
            'weight' => $openerConfig['average_time_to_contact']['weight'],
        ];

        // First kills plus minus
        $firstKillPlusMinus = $playerMatchEvent->first_kills - $playerMatchEvent->first_deaths;
        $scores[] = [
            'score' => $this->normaliseScore(
                $firstKillPlusMinus,
                $openerConfig['first_kills_plus_minus']['score'],
                $openerConfig['first_kills_plus_minus']['higher_better']
            ),
            'weight' => $openerConfig['first_kills_plus_minus']['weight'],
        ];

        // First kill attempts
        $firstKillAttempts = $playerMatchEvent->first_kills + $playerMatchEvent->first_deaths;
        $scores[] = [
            'score' => $this->normaliseScore(
                $firstKillAttempts,
                $openerConfig['first_kill_attempts']['score'],
                $openerConfig['first_kill_attempts']['higher_better']
            ),
            'weight' => $openerConfig['first_kill_attempts']['weight'],
        ];

        // Traded deaths percentage
        $tradedDeathsPercentage = calculatePercentage($playerMatchEvent->total_successful_trades, $playerMatchEvent->total_possible_traded_deaths);
        $scores[] = [
            'score' => $this->normaliseScore(
                $tradedDeathsPercentage,
                $openerConfig['traded_death_percentage']['score'],
                $openerConfig['traded_death_percentage']['higher_better']
            ),
            'weight' => $openerConfig['traded_death_percentage']['weight'],
        ];

        return $this->calculateWeightedMean($scores);
    }

    private function playerCloserScore(PlayerMatchEvent $playerMatchEvent): int
    {
        $closerConfig = $this->config['closer'];
        $scores = [];

        // Average round time to death
        $scores[] = [
            'score' => $this->normaliseScore(
                $playerMatchEvent->average_round_time_of_death,
                $closerConfig['average_round_time_to_death']['score'],
                $closerConfig['average_round_time_to_death']['higher_better']
            ),
            'weight' => $closerConfig['average_round_time_to_death']['weight'],
        ];

        // Average round time to contact
        $scores[] = [
            'score' => $this->normaliseScore(
                $playerMatchEvent->average_time_to_contact,
                $closerConfig['average_round_time_to_contact']['score'],
                $closerConfig['average_round_time_to_contact']['higher_better']
            ),
            'weight' => $closerConfig['average_round_time_to_contact']['weight'],
        ];

        // Clutch win percentage
        $scores[] = [
            'score' => $this->normaliseScore(
                $playerMatchEvent->clutch_win_percentage,
                $closerConfig['clutch_win_percentage']['score'],
                $closerConfig['clutch_win_percentage']['higher_better']
            ),
            'weight' => $closerConfig['clutch_win_percentage']['weight'],
        ];

        // Total clutch attempts
        $scores[] = [
            'score' => $this->normaliseScore(
                $playerMatchEvent->clutch_attempts,
                $closerConfig['total_clutch_attempts']['score'],
                $closerConfig['total_clutch_attempts']['higher_better']
            ),
            'weight' => $closerConfig['total_clutch_attempts']['weight'],
        ];

        return $this->calculateWeightedMean($scores);
    }

    private function playerSupportScore(PlayerMatchEvent $playerMatchEvent): int
    {
        $supportConfig = $this->config['support'];
        $scores = [];

        // Total grenades thrown
        $scores[] = [
            'score' => $this->normaliseScore(
                $playerMatchEvent->grenades_thrown,
                $supportConfig['total_grenades_thrown']['score'],
                $supportConfig['total_grenades_thrown']['higher_better']
            ),
            'weight' => $supportConfig['total_grenades_thrown']['weight'],
        ];

        // Damage dealt from grenades
        $scores[] = [
            'score' => $this->normaliseScore(
                $playerMatchEvent->damage_dealt,
                $supportConfig['damage_dealt_from_grenades']['score'],
                $supportConfig['damage_dealt_from_grenades']['higher_better']
            ),
            'weight' => $supportConfig['damage_dealt_from_grenades']['weight'],
        ];

        // Enemy flash duration
        $scores[] = [
            'score' => $this->normaliseScore(
                $playerMatchEvent->enemy_flash_duration,
                $supportConfig['enemy_flash_duration']['score'],
                $supportConfig['enemy_flash_duration']['higher_better']
            ),
            'weight' => $supportConfig['enemy_flash_duration']['weight'],
        ];

        // Average grenade effectiveness
        $scores[] = [
            'score' => $this->normaliseScore(
                $playerMatchEvent->average_grenade_effectiveness,
                $supportConfig['average_grenade_effectiveness']['score'],
                $supportConfig['average_grenade_effectiveness']['higher_better']
            ),
            'weight' => $supportConfig['average_grenade_effectiveness']['weight'],
        ];

        // Total flashes leading to kills
        $scores[] = [
            'score' => $this->normaliseScore(
                $playerMatchEvent->flashes_leading_to_kills,
                $supportConfig['total_flashes_leading_to_kills']['score'],
                $supportConfig['total_flashes_leading_to_kills']['higher_better']
            ),
            'weight' => $supportConfig['total_flashes_leading_to_kills']['weight'],
        ];

        return $this->calculateWeightedMean($scores);
    }

    private function playerFraggerScore(PlayerMatchEvent $playerMatchEvent): int
    {
        $fraggerConfig = $this->config['fragger'];
        $scores = [];

        // Kill death ratio
        $killDeathRatio = $playerMatchEvent->kills / max($playerMatchEvent->deaths, 1);
        $scores[] = [
            'score' => $this->normaliseScore(
                $killDeathRatio,
                $fraggerConfig['kill_death_ratio']['score'],
                $fraggerConfig['kill_death_ratio']['higher_better']
            ),
            'weight' => $fraggerConfig['kill_death_ratio']['weight'],
        ];

        // Total kills per round
        $totalKillsPerRound = $playerMatchEvent->total_rounds_played > 0 ? $playerMatchEvent->kills / $playerMatchEvent->total_rounds_played : 0;
        $scores[] = [
            'score' => $this->normaliseScore(
                $totalKillsPerRound,
                $fraggerConfig['total_kills_per_round']['score'],
                $fraggerConfig['total_kills_per_round']['higher_better']
            ),
            'weight' => $fraggerConfig['total_kills_per_round']['weight'],
        ];

        // Average damage per round
        $scores[] = [
            'score' => $this->normaliseScore(
                $playerMatchEvent->adr,
                $fraggerConfig['average_damage_per_round']['score'],
                $fraggerConfig['average_damage_per_round']['higher_better']
            ),
            'weight' => $fraggerConfig['average_damage_per_round']['weight'],
        ];

        // Trade kill percentage
        $tradeKillPercentage = calculatePercentage($playerMatchEvent->total_successful_trades, $playerMatchEvent->total_possible_trades);
        $scores[] = [
            'score' => $this->normaliseScore(
                $tradeKillPercentage,
                $fraggerConfig['trade_kill_percentage']['score'],
                $fraggerConfig['trade_kill_percentage']['higher_better']
            ),
            'weight' => $fraggerConfig['trade_kill_percentage']['weight'],
        ];

        // Trade opportunities per round
        $tradeOpportunitiesPerRound = $playerMatchEvent->total_rounds_played > 0 ? $playerMatchEvent->total_possible_trades / $playerMatchEvent->total_rounds_played : 0;
        $scores[] = [
            'score' => $this->normaliseScore(
                $tradeOpportunitiesPerRound,
                $fraggerConfig['trade_opportunities_per_round']['score'],
                $fraggerConfig['trade_opportunities_per_round']['higher_better']
            ),
            'weight' => $fraggerConfig['trade_opportunities_per_round']['weight'],
        ];

        return $this->calculateWeightedMean($scores);
    }

    private function normaliseScore(int|float $metric, int|float $maxScore, bool $higherBetter = true): int
    {
        if ($higherBetter) {
            $score = $metric / $maxScore;
        } else {
            $score = 1 - ($metric / $maxScore);
        }

        $score = max(0, min($score, 1));

        return round($score * 100, 2);
    }

    private function calculateWeightedMean(array $scores): int
    {
        if (empty($scores)) {
            return 0;
        }

        $totalWeightedScore = 0;
        $totalWeight = 0;

        foreach ($scores as $scoreData) {
            $totalWeightedScore += $scoreData['score'] * $scoreData['weight'];
            $totalWeight += $scoreData['weight'];
        }

        if ($totalWeight <= 0) {
            return 0;
        }

        return (int) round($totalWeightedScore / $totalWeight);
    }
}
