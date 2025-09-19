<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class GenerateSteamLinkHashes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'steam:generate-link-hashes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Steam link hashes for existing users who don\'t have them';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $usersWithoutHashes = User::whereNull('steam_link_hash')->get();

        if ($usersWithoutHashes->isEmpty()) {
            $this->info('All users already have Steam link hashes!');

            return;
        }

        $this->info("Found {$usersWithoutHashes->count()} users without Steam link hashes.");

        $bar = $this->output->createProgressBar($usersWithoutHashes->count());
        $bar->start();

        foreach ($usersWithoutHashes as $user) {
            $user->steam_link_hash = hash('sha256', $user->id.config('app.key').time().uniqid());
            $user->saveQuietly();
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Successfully generated Steam link hashes for all users!');
    }
}
