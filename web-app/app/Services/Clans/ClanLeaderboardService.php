<?php

namespace App\Services\Clans;

use App\Enums\LeaderboardType;
use App\Models\Clan;
use App\Models\ClanLeaderboard;
use App\Models\PlayerMatchAimEvent;
use App\Models\PlayerMatchEvent;
use App\Models\User;
use App\Services\Matches\PlayerComplexionService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ClanLeaderboardService
{
    public function __construct(
        private readonly PlayerComplexionService $playerComplexionService
    ) {}

    public function calculateLeaderboard(Clan $clan, string $type, Carbon $startDate, Carbon $endDate): void
    {
        // Get all clan matches in the period
        $matches = $clan->matches()
            ->whereBetween('match_start_time', [$startDate, $endDate])
            ->orWhereBetween('end_timestamp', [$startDate, $endDate])
            ->get();

        if ($matches->isEmpty()) {
            return;
        }

        $matchIds = $matches->pluck('id');
        $userValues = [];

        // Get all clan members
        $clanMembers = $clan->members()->with('user')->get();

        foreach ($clanMembers as $clanMember) {
            $user = $clanMember->user;

            if (! $user || ! $user->steam_id) {
                continue;
            }

            $value = $this->getUserValue($user, $clan, $type, $startDate, $endDate, $matchIds);

            if ($value !== null) {
                $userValues[$user->id] = $value;
            }
        }

        // Sort by value descending
        arsort($userValues);

        // Store leaderboard entries
        $position = 1;
        foreach ($userValues as $userId => $value) {
            ClanLeaderboard::updateOrCreate(
                [
                    'clan_id' => $clan->id,
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'leaderboard_type' => $type,
                    'user_id' => $userId,
                ],
                [
                    'position' => $position++,
                    'value' => $value,
                ]
            );
        }
    }

    public function getLeaderboard(Clan $clan, string $type, Carbon $startDate, Carbon $endDate): Collection
    {
        return ClanLeaderboard::query()
            ->where('clan_id', $clan->id)
            ->where('leaderboard_type', $type)
            ->whereDate('start_date', $startDate->format('Y-m-d'))
            ->whereDate('end_date', $endDate->format('Y-m-d'))
            ->with('user')
            ->ordered()
            ->get();
    }

    public function getUserValue(User $user, Clan $clan, string $type, Carbon $startDate, Carbon $endDate, ?Collection $matchIds = null): ?float
    {
        if (! $user->steam_id) {
            return null;
        }

        if ($matchIds === null) {
            $matches = $clan->matches()
                ->whereBetween('match_start_time', [$startDate, $endDate])
                ->orWhereBetween('end_timestamp', [$startDate, $endDate])
                ->get();
            $matchIds = $matches->pluck('id');
        }

        if ($matchIds->isEmpty()) {
            return null;
        }

        return match ($type) {
            LeaderboardType::AIM->value => $this->calculateAimAverage($user->steam_id, $matchIds),
            LeaderboardType::IMPACT->value => $this->calculateImpactAverage($user->steam_id, $matchIds),
            LeaderboardType::ROUND_SWING->value => $this->calculateRoundSwingAverage($user->steam_id, $matchIds),
            LeaderboardType::FRAGGER->value => $this->calculateRoleAverage($user->steam_id, $matchIds, 'fragger'),
            LeaderboardType::SUPPORT->value => $this->calculateRoleAverage($user->steam_id, $matchIds, 'support'),
            LeaderboardType::OPENER->value => $this->calculateRoleAverage($user->steam_id, $matchIds, 'opener'),
            LeaderboardType::CLOSER->value => $this->calculateRoleAverage($user->steam_id, $matchIds, 'closer'),
            default => null,
        };
    }

    private function calculateAimAverage(string $steamId, Collection $matchIds): ?float
    {
        $aimEvents = PlayerMatchAimEvent::query()
            ->whereIn('match_id', $matchIds)
            ->where('player_steam_id', $steamId)
            ->get();

        if ($aimEvents->isEmpty()) {
            return null;
        }

        return (float) $aimEvents->avg('aim_rating');
    }

    private function calculateImpactAverage(string $steamId, Collection $matchIds): ?float
    {
        $matchEvents = PlayerMatchEvent::query()
            ->whereIn('match_id', $matchIds)
            ->where('player_steam_id', $steamId)
            ->get();

        if ($matchEvents->isEmpty()) {
            return null;
        }

        return (float) $matchEvents->avg('average_impact');
    }

    private function calculateRoundSwingAverage(string $steamId, Collection $matchIds): ?float
    {
        $matchEvents = PlayerMatchEvent::query()
            ->whereIn('match_id', $matchIds)
            ->where('player_steam_id', $steamId)
            ->get();

        if ($matchEvents->isEmpty()) {
            return null;
        }

        return (float) $matchEvents->avg('match_swing_percent');
    }

    private function calculateRoleAverage(string $steamId, Collection $matchIds, string $role): ?float
    {
        $scores = [];

        foreach ($matchIds as $matchId) {
            $complexion = $this->playerComplexionService->get($steamId, $matchId);

            if (! empty($complexion) && isset($complexion[$role])) {
                $scores[] = $complexion[$role];
            }
        }

        if (empty($scores)) {
            return null;
        }

        return (float) (array_sum($scores) / count($scores));
    }
}
