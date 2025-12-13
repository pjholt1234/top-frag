<?php

namespace App\Jobs;

use App\Enums\LeaderboardType;
use App\Models\Clan;
use App\Services\Clans\ClanLeaderboardService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CalculateClanLeaderboards implements ShouldQueue
{
    use Queueable;

    private ClanLeaderboardService $leaderboardService;

    public function __construct()
    {
        // Service will be resolved in handle method
    }

    public function handle(): void
    {
        $this->leaderboardService = app(ClanLeaderboardService::class);
        Log::info('Starting CalculateClanLeaderboards job');

        $clans = Clan::all();

        if ($clans->isEmpty()) {
            Log::info('No clans found, skipping leaderboard calculation');

            return;
        }

        $now = Carbon::now();
        $weekStart = $now->copy()->subDays(7)->startOfDay();
        $weekEnd = $now->copy()->endOfDay();
        $monthStart = $now->copy()->subDays(30)->startOfDay();
        $monthEnd = $now->copy()->endOfDay();

        $leaderboardTypes = [
            LeaderboardType::AIM->value,
            LeaderboardType::IMPACT->value,
            LeaderboardType::ROUND_SWING->value,
            LeaderboardType::FRAGGER->value,
            LeaderboardType::SUPPORT->value,
            LeaderboardType::OPENER->value,
            LeaderboardType::CLOSER->value,
        ];

        foreach ($clans as $clan) {
            Log::info('Calculating leaderboards for clan', [
                'clan_id' => $clan->id,
                'clan_name' => $clan->name,
            ]);

            foreach ($leaderboardTypes as $type) {
                try {
                    // Calculate week leaderboard
                    $this->leaderboardService->calculateLeaderboard(
                        $clan,
                        $type,
                        $weekStart,
                        $weekEnd
                    );

                    // Calculate month leaderboard
                    $this->leaderboardService->calculateLeaderboard(
                        $clan,
                        $type,
                        $monthStart,
                        $monthEnd
                    );
                } catch (\Exception $e) {
                    Log::error('Error calculating leaderboard', [
                        'clan_id' => $clan->id,
                        'type' => $type,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        Log::info('CalculateClanLeaderboards job completed');
    }
}
