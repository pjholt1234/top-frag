<?php

namespace App\Services\MatchTimeExtraction;

use App\Models\GameMatch;
use Carbon\Carbon;

interface MatchTimeStrategy
{
    /**
     * Extract match start time from the original file name.
     *
     * @param  string|null  $originalFileName  The original file name of the demo
     * @param  GameMatch  $gameMatch  The game match to validate against
     * @return Carbon|null The match start time, or null if extraction fails
     */
    public function extract(?string $originalFileName, GameMatch $gameMatch): ?Carbon;
}
