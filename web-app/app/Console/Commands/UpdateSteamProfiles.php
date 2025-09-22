<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\SteamAPIConnector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateSteamProfiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'steam:update-profiles {--force : Force update even if recently updated}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Steam profile data for registered users with Steam IDs';

    public function __construct(
        private readonly SteamAPIConnector $steamApiConnector
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Steam profile update for registered users...');

        $force = $this->option('force');

        // Get registered users with Steam IDs, optionally filtering by recent updates
        $query = User::whereNotNull('steam_id');

        if (! $force) {
            // Only update profiles that haven't been updated in the last 24 hours
            $query->where(function ($q) {
                $q->whereNull('steam_profile_updated_at')
                    ->orWhere('steam_profile_updated_at', '<', now()->subDay());
            });
        }

        $users = $query->get();

        if ($users->isEmpty()) {
            $this->info('No registered users found that need Steam profile updates.');

            return;
        }

        $this->info("Found {$users->count()} registered users to update.");

        $steamIds = $users->pluck('steam_id')->toArray();

        // Process in batches to respect Steam API rate limits
        $batchSize = 10;
        $batches = array_chunk($steamIds, $batchSize);

        $updated = 0;
        $failed = 0;

        foreach ($batches as $batch) {
            $this->info('Processing batch of '.count($batch).' users...');

            try {
                $steamProfiles = $this->steamApiConnector->getPlayerSummaries($batch);

                if ($steamProfiles) {
                    foreach ($batch as $steamId) {
                        $user = $users->firstWhere('steam_id', $steamId);

                        if ($user && isset($steamProfiles[$steamId])) {
                            $profile = $steamProfiles[$steamId];

                            $user->update([
                                'steam_persona_name' => $profile['persona_name'],
                                'steam_profile_url' => $profile['profile_url'],
                                'steam_avatar' => $profile['avatar'],
                                'steam_avatar_medium' => $profile['avatar_medium'],
                                'steam_avatar_full' => $profile['avatar_full'],
                                'steam_persona_state' => $profile['persona_state'],
                                'steam_community_visibility_state' => $profile['community_visibility_state'],
                                'steam_profile_updated_at' => now(),
                            ]);

                            $updated++;
                            $this->line("Updated profile for user: {$user->name} (Steam ID: {$steamId})");
                        } else {
                            $failed++;
                            $this->warn("Failed to get profile data for Steam ID: {$steamId}");
                        }
                    }
                } else {
                    $failed += count($batch);
                    $this->warn('Failed to fetch Steam profiles for batch');
                }

                // Add delay between batches to respect rate limits
                if (count($batches) > 1) {
                    sleep(2);
                }
            } catch (\Exception $e) {
                $failed += count($batch);
                $this->error('Error processing batch: '.$e->getMessage());
                Log::error('Steam profile update batch failed', [
                    'batch' => $batch,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info('Steam profile update completed!');
        $this->info("Updated: {$updated} users");
        $this->info("Failed: {$failed} users");
    }
}
