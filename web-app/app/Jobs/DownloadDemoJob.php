<?php

namespace App\Jobs;

use App\Models\DemoProcessingJob;
use App\Models\GameMatch;
use App\Services\DemoDownloadService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DownloadDemoJob implements ShouldQueue
{
    use Queueable;

    private readonly DemoDownloadService $demoDownloadService;

    public function __construct(
        private readonly string $sharecode,
        private readonly int $userId
    ) {
        $this->demoDownloadService = app(DemoDownloadService::class);
    }

    public function handle(): void
    {
        Log::info('Starting demo download', [
            'sharecode' => $this->sharecode,
            'user_id' => $this->userId,
        ]);

        $demoUrl = $this->demoDownloadService->fetchDemoUrl($this->sharecode);
        if (! $demoUrl) {
            Log::error('Failed to get demo URL', ['sharecode' => $this->sharecode]);

            return;
        }

        $demoFilePath = $this->demoDownloadService->downloadDemo($this->sharecode);
        if (! $demoFilePath) {
            Log::error('Failed to download demo', ['sharecode' => $this->sharecode]);

            return;
        }

        // Create match record
        $match = GameMatch::create([
            'sharecode' => $this->sharecode,
            'demo_url' => $demoUrl,
            'uploaded_by' => null,
        ]);

        $job = DemoProcessingJob::create([
            'match_id' => $match->id,
            'user_id' => null,
        ]);

        // Dispatch parsing job with high priority
        ParseDemo::dispatch($demoFilePath, null, $match->id)
            ->onQueue('high');

        Log::info('Demo download completed and queued for parsing', [
            'sharecode' => $this->sharecode,
            'match_id' => $match->id,
            'job_id' => $job->uuid,
        ]);
    }
}
