<?php

namespace App\Console\Commands;

use App\Jobs\ValveDemoRetrieval;
use Illuminate\Console\Command;

class ValveDemoRetrievalCommand extends Command
{
    protected $signature = 'valve:demo-retrieval';

    protected $description = 'Retrieve new demo files from Steam for users with sharecodes';

    public function handle(): int
    {
        $this->info('Dispatching ValveDemoRetrieval job...');

        ValveDemoRetrieval::dispatch();

        $this->info('ValveDemoRetrieval job dispatched successfully.');

        return Command::SUCCESS;
    }
}
