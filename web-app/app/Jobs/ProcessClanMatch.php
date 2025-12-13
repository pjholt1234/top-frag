<?php

namespace App\Jobs;

use App\Models\Clan;
use App\Models\GameMatch;
use App\Services\Clans\ClanMatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessClanMatch implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $matchId
    ) {}

    public function handle(ClanMatchService $clanMatchService): void
    {
        $match = GameMatch::find($this->matchId);

        if (! $match) {
            Log::warning('Match not found for ProcessClanMatch', [
                'match_id' => $this->matchId,
            ]);

            return;
        }

        Log::info('Processing clan match', [
            'match_id' => $this->matchId,
        ]);

        $clans = Clan::all();

        foreach ($clans as $clan) {
            try {
                $added = $clanMatchService->checkAndAddMatch($clan, $match);

                if ($added) {
                    Log::info('Added match to clan', [
                        'clan_id' => $clan->id,
                        'match_id' => $this->matchId,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Error processing clan match', [
                    'clan_id' => $clan->id,
                    'match_id' => $this->matchId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
