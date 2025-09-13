<?php

namespace App\Services\Matches;

use App\Models\GameMatch;
use App\Models\PlayerMatchEvent;
use App\Models\User;
use App\Services\MatchCacheManager;

class PlayerStatsService
{
    use MatchAccessTrait;

    // Opener metrics
    private const LOWEST_AVERAGE_TIME_OF_DEATH = 25;

    private const LOWEST_AVERAGE_TIME_TO_CONTACT = 20;

    private const MAX_FIRST_KILLS_PLUS_MINUS = 3;

    private const MAX_FIRST_KILL_ATTEMPTS = 4;

    private const MAX_TRADED_DEATH_PERCENTAGE = 50;

    // Closer metrics
    private const MAX_AVERAGE_ROUND_TIME_TO_DEATH = 40;

    private const MAX_AVERAGE_ROUND_TIME_TO_CONTACT = 35;

    private const MAX_CLUTCH_WIN_PERCENTAGE = 25;

    private const MAX_TOTAL_CLUTCH_ATTEMPTS = 5;

    // Support metrics
    private const MAX_TOTAL_GRENADES_THROWN = 25;

    private const MAX_DAMAGE_DEALTH_FROM_GRENADES = 200;

    private const MAX_ENEMY_FLASH_DURATION = 30;

    private const MAX_AVERAGE_GRENADE_EFFECTIVENESS = 50;

    private const MAX_TOTAL_FLASHES_LEADING_TO_KILLS = 5;

    // Fragger metrics
    private const MAX_KILL_DEATH_RATION = 1.5;

    private const MAX_TOTAL_KILLS_PER_ROUND = 0.9;

    private const MAX_AVERAGE_DAMAGE_PER_ROUND = 90;

    private const MAX_TRADE_KILL_PERCENTAGE = 50;

    private const MAX_TRADE_OPPORTUNIES_PER_ROUND = 1.5;

    public function get(User $user, array $filters, int $matchId): array
    {
        $cacheKey = $this->getCacheKey($filters);

        return MatchCacheManager::remember($cacheKey, $matchId, function () use ($user, $filters, $matchId) {
            return $this->buildStats($user, $filters, $matchId);
        });
    }

    private function getCacheKey(array $filters): string
    {
        $filterHash = empty($filters) ? 'default' : md5(serialize($filters));

        return "player-stats_{$filterHash}";
    }

    private function buildStats(User $user, array $filters, int $matchId): array
    {
        // Get the match first to access players
        $match = $this->getMatchForUser($user, $matchId);
        if (! $match) {
            return [];
        }

        // If no player specified, return just the players list and current user info
        if (empty($filters['player_steam_id'])) {
            return [
                'players' => $this->getAvailablePlayers($match),
                'current_user_steam_id' => $user->steam_id,
            ];
        }

        $playerMatchEvent = PlayerMatchEvent::query()
            ->where('match_id', $matchId)
            ->where('player_steam_id', $filters['player_steam_id'])
            ->first();

        if (! $playerMatchEvent) {
            return [];
        }

        return [
            'player_complexion' => $this->getPlayerComplexion($playerMatchEvent),
            'trades' => $this->getPlayerTradeStats($playerMatchEvent),
            'clutch_stats' => $this->getPlayerClutchStats($playerMatchEvent),
            'deep_dive' => $this->getDeepDiveStats($playerMatchEvent),
            'players' => $this->getAvailablePlayers($match),
            'current_user_steam_id' => $user->steam_id,
        ];
    }

    private function getPlayerComplexion(PlayerMatchEvent $playerMatchEvent): array
    {
        return [
            'opener' => $this->playerOpenerScore($playerMatchEvent),
            'closer' => $this->playerCloserScore($playerMatchEvent),
            'support' => $this->playerSupportScore($playerMatchEvent),
            'fragger' => $this->playerFraggerScore($playerMatchEvent),
        ];
    }

    private function getPlayerTradeStats(PlayerMatchEvent $playerMatchEvent): array
    {
        return [
            'total_successful_trades' => $playerMatchEvent->total_successful_trades,
            'total_possible_trades' => $playerMatchEvent->total_possible_trades,
            'total_traded_deaths' => $playerMatchEvent->total_traded_deaths,
            'total_possible_traded_deaths' => $playerMatchEvent->total_possible_traded_deaths,
        ];
    }

    private function getDeepDiveStats(PlayerMatchEvent $playerMatchEvent): array
    {
        return [
            'round_swing' => 0,
            'impact' => 0,
            'opening_duels' => [
                'first_kills' => $playerMatchEvent->first_kills,
                'first_deaths' => $playerMatchEvent->first_deaths,
            ],
        ];
    }

    private function getPlayerClutchStats(PlayerMatchEvent $playerMatchEvent): array
    {
        return [
            '1v1' => [
                'clutch_wins_1v1' => $playerMatchEvent->clutch_wins_1v1,
                'clutch_attempts_1v1' => $playerMatchEvent->clutch_attempts_1v1,
                'clutch_win_percentage_1v1' => calculatePercentage($playerMatchEvent->clutch_wins_1v1, $playerMatchEvent->clutch_attempts_1v1),
            ],
            '1v2' => [
                'clutch_wins_1v2' => $playerMatchEvent->clutch_wins_1v2,
                'clutch_attempts_1v2' => $playerMatchEvent->clutch_attempts_1v2,
                'clutch_win_percentage_1v2' => calculatePercentage($playerMatchEvent->clutch_wins_1v2, $playerMatchEvent->clutch_attempts_1v2),
            ],
            '1v3' => [
                'clutch_wins_1v3' => $playerMatchEvent->clutch_wins_1v3,
                'clutch_attempts_1v3' => $playerMatchEvent->clutch_attempts_1v3,
                'clutch_win_percentage_1v3' => calculatePercentage($playerMatchEvent->clutch_wins_1v3, $playerMatchEvent->clutch_attempts_1v3),
            ],
            '1v4' => [
                'clutch_wins_1v4' => $playerMatchEvent->clutch_wins_1v4,
                'clutch_attempts_1v4' => $playerMatchEvent->clutch_attempts_1v4,
                'clutch_win_percentage_1v4' => calculatePercentage($playerMatchEvent->clutch_wins_1v4, $playerMatchEvent->clutch_attempts_1v4),
            ],
            '1v5' => [
                'clutch_wins_1v5' => $playerMatchEvent->clutch_wins_1v5,
                'clutch_attempts_1v5' => $playerMatchEvent->clutch_attempts_1v5,
                'clutch_win_percentage_1v5' => calculatePercentage($playerMatchEvent->clutch_wins_1v5, $playerMatchEvent->clutch_attempts_1v5),
            ],
        ];
    }

    private function playerOpenerScore(PlayerMatchEvent $playerMatchEvent): int
    {
        $averageRoundTimeOfDeathScore = $this->normaliseScore(
            $playerMatchEvent->average_round_time_of_death,
            self::LOWEST_AVERAGE_TIME_OF_DEATH,
            false
        );

        $averageRoundTimeOfContactScore = $this->normaliseScore(
            $playerMatchEvent->average_time_to_contact,
            self::LOWEST_AVERAGE_TIME_TO_CONTACT,
            false
        );

        $firstKillPlusMinus = $playerMatchEvent->first_kills - $playerMatchEvent->first_deaths;
        $firstKillPlusMinusScore = $this->normaliseScore(
            $firstKillPlusMinus,
            self::MAX_FIRST_KILLS_PLUS_MINUS
        );

        $firstKillAttempts = $playerMatchEvent->first_kills + $playerMatchEvent->first_deaths;
        $firstKillAttemptsScore = $this->normaliseScore(
            $firstKillAttempts,
            self::MAX_FIRST_KILL_ATTEMPTS
        );

        $tradedDeathsPercentage = calculatePercentage($playerMatchEvent->total_successful_trades, $playerMatchEvent->total_possible_traded_deaths);
        $firstKillAttemptsScore = $this->normaliseScore(
            $tradedDeathsPercentage,
            self::MAX_TRADED_DEATH_PERCENTAGE
        );

        return (int) calculateMean([
            $averageRoundTimeOfDeathScore,
            $averageRoundTimeOfContactScore,
            $firstKillPlusMinusScore,
            $firstKillAttemptsScore,
            $firstKillAttemptsScore,
        ]);
    }

    private function playerCloserScore(PlayerMatchEvent $playerMatchEvent): int
    {
        $averageRoundTimeToDeathScore = $this->normaliseScore(
            $playerMatchEvent->average_round_time_of_death,
            self::MAX_AVERAGE_ROUND_TIME_TO_DEATH,
        );

        $averageRoundTimeToContactScore = $this->normaliseScore(
            $playerMatchEvent->average_time_to_contact,
            self::MAX_AVERAGE_ROUND_TIME_TO_CONTACT,
        );

        $clutchWinPercentage = $playerMatchEvent->clutch_win_percentage;
        $clutchWinPercentageScore = $this->normaliseScore(
            $clutchWinPercentage,
            self::MAX_CLUTCH_WIN_PERCENTAGE,
        );

        $totalClutchAttemptsScore = $this->normaliseScore(
            $playerMatchEvent->clutch_attempts,
            self::MAX_TOTAL_CLUTCH_ATTEMPTS,
        );

        return (int) calculateMean([
            $averageRoundTimeToDeathScore,
            $averageRoundTimeToContactScore,
            $clutchWinPercentageScore,
            $totalClutchAttemptsScore,
        ]);
    }

    private function playerSupportScore(PlayerMatchEvent $playerMatchEvent): int
    {
        $totalGrenadesThrownScore = $this->normaliseScore(
            $playerMatchEvent->grenades_thrown,
            self::MAX_TOTAL_GRENADES_THROWN,
        );

        $damageDealtFromGrenadesScore = $this->normaliseScore(
            $playerMatchEvent->damage_dealt,
            self::MAX_DAMAGE_DEALTH_FROM_GRENADES,
        );

        $enemyFlashDurationScore = $this->normaliseScore(
            $playerMatchEvent->enemy_flash_duration,
            self::MAX_ENEMY_FLASH_DURATION,
        );

        $averageGrenadeEffectivenessScore = $this->normaliseScore(
            $playerMatchEvent->average_grenade_effectiveness,
            self::MAX_AVERAGE_GRENADE_EFFECTIVENESS,
        );

        $totalFlashesLeadingToKillsScore = $this->normaliseScore(
            $playerMatchEvent->flashes_leading_to_kills,
            self::MAX_TOTAL_FLASHES_LEADING_TO_KILLS,
        );

        return (int) calculateMean([
            $totalGrenadesThrownScore,
            $damageDealtFromGrenadesScore,
            $enemyFlashDurationScore,
            $averageGrenadeEffectivenessScore,
            $totalFlashesLeadingToKillsScore,
        ]);
    }

    private function playerFraggerScore(PlayerMatchEvent $playerMatchEvent): int
    {
        $killDeathRatio = $playerMatchEvent->kills / max($playerMatchEvent->deaths, 1);
        $killDeathRatioScore = $this->normaliseScore(
            $killDeathRatio,
            self::MAX_KILL_DEATH_RATION,
        );

        $totalKillsPerRound = $playerMatchEvent->total_rounds_played > 0 ? $playerMatchEvent->kills / $playerMatchEvent->total_rounds_played : 0;
        $totalKillsPerRoundScore = $this->normaliseScore(
            $totalKillsPerRound,
            self::MAX_TOTAL_KILLS_PER_ROUND,
        );

        $averageDamagePerRoundScore = $this->normaliseScore(
            $playerMatchEvent->adr,
            self::MAX_AVERAGE_DAMAGE_PER_ROUND,
        );

        $tradeKillPercentage = calculatePercentage($playerMatchEvent->total_successful_trades, $playerMatchEvent->total_possible_trades);
        $tradeKillPercentageScore = $this->normaliseScore(
            $tradeKillPercentage,
            self::MAX_TRADE_KILL_PERCENTAGE,
        );

        $tradeOpportunitiesPerRound = $playerMatchEvent->total_rounds_played > 0 ? $playerMatchEvent->total_possible_trades / $playerMatchEvent->total_rounds_played : 0;
        $tradeOpportunitiesPerRoundScore = $this->normaliseScore(
            $tradeOpportunitiesPerRound,
            self::MAX_TRADE_OPPORTUNIES_PER_ROUND,
        );

        return (int) calculateMean([
            $killDeathRatioScore,
            $totalKillsPerRoundScore,
            $averageDamagePerRoundScore,
            $tradeKillPercentageScore,
            $tradeOpportunitiesPerRoundScore,
        ]);
    }

    private function normaliseScore(int|float $metric, int|float $maxScore, $higherBetter = true): int
    {
        if ($higherBetter) {
            $score = $metric / $maxScore;
        } else {
            $score = 1 - ($metric / $maxScore);
        }

        $score = max(0, min($score, 1));

        return round($score * 100, 2);
    }

    private function getAvailablePlayers(GameMatch $match): array
    {
        return $match->players->map(function ($player) {
            return [
                'steam_id' => $player->steam_id,
                'name' => $player->name,
            ];
        })->toArray();
    }

    private function getMatchForUser(User $user, int $matchId): ?GameMatch
    {
        if (! $user->player) {
            return null;
        }

        return $user->player->matches()
            ->where('matches.id', $matchId)
            ->first();
    }
}
