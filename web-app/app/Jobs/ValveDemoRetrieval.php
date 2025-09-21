<?php

namespace App\Jobs;

use App\Models\DemoProcessingJob;
use App\Models\GameMatch;
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
            $this->processUser($user);
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

    private function processUser(User $user): void
    {
        Log::info('Processing user for Steam demo retrieval', [
            'user_id' => $user->id,
            'steam_id' => $user->steam_id,
            'current_sharecode' => $user->steam_sharecode,
        ]);

        $processedCount = 0;
        $currentSharecode = $user->steam_sharecode;

        try {
            while ($processedCount < $this->maxSharecodesPerRun) {
                $nextSharecode = $this->steamApiService->getNextMatchSharingCode(
                    $user->steam_id,
                    $user->steam_game_auth_code,
                    $currentSharecode
                );

                // Handle the "n/a" case from Steam API
                if (! $nextSharecode || $nextSharecode === 'n/a') {
                    Log::info('No more sharecodes available for user (reached end of history)', [
                        'user_id' => $user->id,
                        'steam_id' => $user->steam_id,
                        'processed_count' => $processedCount,
                    ]);
                    break;
                }

                // Check if this sharecode already exists
                if ($this->sharecodeAlreadyExists($nextSharecode)) {
                    Log::info('Sharecode already exists, updating user sharecode and continuing', [
                        'user_id' => $user->id,
                        'sharecode' => $nextSharecode,
                        'processed_count' => $processedCount,
                    ]);
                    $currentSharecode = $nextSharecode;
                    $this->updateUserSharecode($user, $nextSharecode);

                    continue;
                }

                // Get the demo URL first
                $demoUrl = $this->demoDownloadService->fetchDemoUrl($nextSharecode);
                if (! $demoUrl) {
                    Log::error('Failed to get demo URL for user', [
                        'user_id' => $user->id,
                        'sharecode' => $nextSharecode,
                        'processed_count' => $processedCount,
                    ]);
                    // Continue to next sharecode instead of breaking
                    $currentSharecode = $nextSharecode;
                    $this->updateUserSharecode($user, $nextSharecode);

                    continue;
                }

                $demoFilePath = $this->demoDownloadService->downloadDemo($nextSharecode);

                if (! $demoFilePath) {
                    Log::error('Failed to download demo for user', [
                        'user_id' => $user->id,
                        'sharecode' => $nextSharecode,
                        'processed_count' => $processedCount,
                    ]);
                    // Continue to next sharecode instead of breaking
                    $currentSharecode = $nextSharecode;
                    $this->updateUserSharecode($user, $nextSharecode);

                    continue;
                }

                // Successfully processed a new demo
                $this->queueDemoForParsing($user, $nextSharecode, $demoFilePath, $demoUrl);
                $currentSharecode = $nextSharecode;
                $this->updateUserSharecode($user, $nextSharecode);
                $processedCount++;

                Log::info('Successfully processed sharecode for user', [
                    'user_id' => $user->id,
                    'sharecode' => $nextSharecode,
                    'processed_count' => $processedCount,
                ]);
            }

            // Update last processed time after all sharecodes are processed
            $this->updateUserLastProcessed($user);

            Log::info('Completed processing user for Steam demo retrieval', [
                'user_id' => $user->id,
                'total_processed' => $processedCount,
                'final_sharecode' => $currentSharecode,
            ]);
        } catch (\Exception $e) {
            Log::error('Error processing user for Steam demo retrieval', [
                'user_id' => $user->id,
                'processed_count' => $processedCount,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function sharecodeAlreadyExists(string $sharecode): bool
    {
        return GameMatch::where('sharecode', $sharecode)->exists();
    }

    private function queueDemoForParsing(User $user, string $sharecode, string $demoFilePath, string $demoUrl): void
    {
        $match = GameMatch::create([
            'sharecode' => $sharecode,
            'demo_url' => $demoUrl,
            'uploaded_by' => null,
        ]);

        $job = DemoProcessingJob::create([
            'match_id' => $match->id,
            'user_id' => null,
        ]);

        ParseDemo::dispatch($demoFilePath, null, $match->id);

        Log::info('Queued demo for parsing', [
            'user_id' => $user->id,
            'match_id' => $match->id,
            'job_id' => $job->uuid,
            'sharecode' => $sharecode,
            'demo_url' => $demoUrl,
        ]);
    }

    private function updateUserLastProcessed(User $user): void
    {
        $user->update(['steam_last_processed_at' => now()]);
    }

    private function updateUserSharecode(User $user, string $newSharecode): void
    {
        $previousSharecode = $user->steam_sharecode;
        $user->update(['steam_sharecode' => $newSharecode]);

        Log::info('Updated user steam sharecode', [
            'user_id' => $user->id,
            'previous_sharecode' => $previousSharecode,
            'new_sharecode' => $newSharecode,
        ]);
    }
}
