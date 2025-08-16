<?php

namespace App\Observers;

use App\Models\DemoProcessingJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DemoProcessingJobObserver
{
    public function creating(DemoProcessingJob $job): void
    {
        $job->started_at = now();
        if (empty($job->uuid)) {
            $job->uuid = Str::uuid();
        }
    }

    public function updated(DemoProcessingJob $job): void
    {
        Log::info('DemoProcessingJobObserver updated', ['job' => $job->processing_status]);
    }
}
