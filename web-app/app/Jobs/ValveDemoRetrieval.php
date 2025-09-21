<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\DemoDownloadService;
use App\Services\ParserServiceConnector;
use App\Services\SteamAPIConnector;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ValveDemoRetrieval implements ShouldQueue
{
    use Queueable;

    private readonly SteamAPIConnector $steamApiService;

    private readonly DemoDownloadService $demoDownloadService;

    private readonly ParserServiceConnector $parserServiceConnector;

    private readonly int $maxSharecodesPerRun;

    public function __construct()
    {
        $this->steamApiService = app(SteamAPIConnector::class);
        $this->demoDownloadService = app(DemoDownloadService::class);
        $this->parserServiceConnector = app(ParserServiceConnector::class);
        $this->maxSharecodesPerRun = config('services.steam.max_sharecodes_per_run', 50);
    }

    public function handle(): void
    {
        Log::info('Starting ValveDemoRetrieval job');

        if (! $this->checkServicesHealth()) {
            Log::warning('Services are not healthy, skipping ValveDemoRetrieval job');

            return;
        }

        $this->demoDownloadService->cleanupOldTempFiles();

        $users = $this->getEligibleUsers();

        if ($users->isEmpty()) {
            Log::info('No eligible users found for Steam demo retrieval');

            return;
        }

        Log::info('Processing users for Steam demo retrieval', [
            'user_count' => $users->count(),
        ]);

        foreach ($users as $user) {
            ProcessUserSharecodesJob::dispatch($user->id)
                ->onQueue('steam-processing');
        }

        Log::info('ValveDemoRetrieval job completed');
    }

    private function checkServicesHealth(): bool
    {
        $steamApiHealthy = $this->steamApiService->checkServiceHealth();

        if (! $steamApiHealthy) {
            Log::error('Steam API is not healthy, skipping job');

            return false;
        }

        $parserServiceHealthy = $this->checkParserServiceHealth();

        if (! $parserServiceHealthy) {
            Log::error('Parser service is not healthy, skipping job');

            return false;
        }

        return true;
    }

    private function checkParserServiceHealth(): bool
    {
        try {
            $this->parserServiceConnector->checkServiceHealth();

            return true;
        } catch (\Exception $e) {
            Log::error('Parser service health check failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function getEligibleUsers()
    {
        return User::whereNotNull('steam_sharecode')
            ->whereNotNull('steam_game_auth_code')
            ->where('steam_match_processing_enabled', true)
            ->where(function ($query) {
                $query->whereNull('steam_last_processed_at')
                    ->orWhere('steam_last_processed_at', '<', now()->subMinutes(15));
            })
            ->get();
    }
}
