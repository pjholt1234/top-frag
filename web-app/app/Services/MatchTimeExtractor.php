<?php

namespace App\Services;

use App\Enums\MatchType;
use App\Models\DemoProcessingJob;
use App\Services\MatchTimeExtraction\FaceItMatchStrategy;
use App\Services\MatchTimeExtraction\MatchTimeStrategy;
use Carbon\Carbon;

class MatchTimeExtractor
{
    private ?MatchTimeStrategy $strategy = null;

    public function __construct(
        private readonly DemoProcessingJob $job
    ) {
        $this->determineStrategy();
    }

    /**
     * Extract match start time using the appropriate strategy.
     *
     * @return Carbon|null The match start time, or null if extraction fails
     */
    public function extract(): ?Carbon
    {
        if ($this->strategy === null) {
            return null;
        }

        $match = $this->job->match;

        if (! $match) {
            return null;
        }

        $originalFileName = $this->job->original_file_name;

        if (empty($originalFileName)) {
            return null;
        }

        return $this->strategy->extract($originalFileName, $match);
    }

    /**
     * Determine the appropriate strategy based on match type.
     */
    private function determineStrategy(): void
    {
        $match = $this->job->match;

        if (! $match || ! $match->match_type) {
            return;
        }

        match ($match->match_type) {
            MatchType::FACEIT => $this->strategy = new FaceItMatchStrategy(app(FaceITRepository::class)),
            default => $this->strategy = null,
        };
    }
}
