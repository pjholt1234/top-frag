<?php

namespace App\Observers;

use App\Models\DemoProcessingJob;
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
        // If job status or progress changed, invalidate match cache
        if ($job->wasChanged('processing_status') || $job->wasChanged('progress_percentage')) {
            $job->match->invalidateMatchCache();
        }
    }

    public function completed(DemoProcessingJob $job): void
    {
        $job->match->invalidateMatchCache();
    }
}
