<?php

namespace App\Console\Commands;

use App\Jobs\CalculateClanLeaderboards;
use Illuminate\Console\Command;

class CalculateClanLeaderboardsCommand extends Command
{
    protected $signature = 'clans:calculate-leaderboards {--sync : Run synchronously instead of dispatching to queue}';

    protected $description = 'Calculate leaderboards for all clans';

    public function handle(): int
    {
        if ($this->option('sync')) {
            $this->info('Running CalculateClanLeaderboards synchronously...');

            $job = new CalculateClanLeaderboards;
            $job->handle();

            $this->info('CalculateClanLeaderboards completed successfully.');
        } else {
            $this->info('Dispatching CalculateClanLeaderboards job to queue...');

            CalculateClanLeaderboards::dispatch();

            $this->info('CalculateClanLeaderboards job dispatched successfully.');
        }

        return Command::SUCCESS;
    }
}
