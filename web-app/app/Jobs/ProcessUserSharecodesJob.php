<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\SteamAPIConnector;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessUserSharecodesJob implements ShouldQueue
{
    use Queueable;

    private readonly SteamAPIConnector $steamApiService;

    private readonly int $maxSharecodesPerJob;

    public function __construct(
        private readonly int $userId
    ) {
        $this->steamApiService = app(SteamAPIConnector::class);
        $this->maxSharecodesPerJob = config('services.steam.max_sharecodes_per_job', 10);
    }

    public function handle(): void
    {
        $user = User::findOrFail($this->userId);

        Log::info('Processing user sharecodes', [
            'user_id' => $user->id,
            'steam_id' => $user->steam_id,
        ]);

        $processedCount = 0;
        $currentSharecode = $user->steam_sharecode;

        while ($processedCount < $this->maxSharecodesPerJob) {
            $nextSharecode = $this->steamApiService->getNextMatchSharingCode(
                $user->steam_id,
                $user->steam_game_auth_code,
                $currentSharecode
            );

            if (! $nextSharecode || $nextSharecode === 'n/a') {
                break;
            }

            if ($this->sharecodeAlreadyExists($nextSharecode)) {
                $currentSharecode = $nextSharecode;
                $this->updateUserSharecode($user, $nextSharecode);

                continue;
            }

            DownloadDemoJob::dispatch($nextSharecode, $user->id)
                ->onQueue('demo-download');

            $currentSharecode = $nextSharecode;
            $this->updateUserSharecode($user, $nextSharecode);
            $processedCount++;
        }

        // Update last processed time
        $user->update(['steam_last_processed_at' => now()]);

        Log::info('Completed processing user sharecodes', [
            'user_id' => $user->id,
            'processed_count' => $processedCount,
        ]);
    }

    private function sharecodeAlreadyExists(string $sharecode): bool
    {
        return \App\Models\GameMatch::where('sharecode', $sharecode)->exists();
    }

    private function updateUserSharecode(User $user, string $newSharecode): void
    {
        $user->update(['steam_sharecode' => $newSharecode]);
    }
}
