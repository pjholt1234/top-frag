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
}
