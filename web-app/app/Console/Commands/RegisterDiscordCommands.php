<?php

namespace App\Console\Commands;

use App\Enums\LeaderboardType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RegisterDiscordCommands extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'discord:register-commands {--guild= : Register commands for a specific guild ID (optional, defaults to global)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Register Discord slash commands with Discord API';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $botToken = config('services.discord.bot_token');
        $applicationId = config('services.discord.application_id');

        if (! $botToken || ! $applicationId) {
            $this->error('Discord bot token or application ID not configured. Check your .env file.');

            return Command::FAILURE;
        }

        $guildId = $this->option('guild');
        $url = $guildId
            ? "https://discord.com/api/v10/applications/{$applicationId}/guilds/{$guildId}/commands"
            : "https://discord.com/api/v10/applications/{$applicationId}/commands";

        $this->info($guildId ? "Registering commands for guild: {$guildId}" : 'Registering global commands');

        $commands = [
            [
                'name' => 'setup',
                'description' => 'Link this Discord server to your Top Frag clan',
                'type' => 1, // CHAT_INPUT
            ],
            [
                'name' => 'unlink-clan',
                'description' => 'Unlink your clan from this Discord server',
                'type' => 1, // CHAT_INPUT
            ],
            [
                'name' => 'members',
                'description' => 'List all members in this clan',
                'type' => 1, // CHAT_INPUT
            ],
            [
                'name' => 'leaderboard',
                'description' => 'View the weekly leaderboard for this clan',
                'type' => 1, // CHAT_INPUT
                'options' => [
                    [
                        'name' => 'leaderboard_type',
                        'description' => 'The type of leaderboard to view',
                        'type' => 3, // STRING
                        'required' => true,
                        'choices' => array_map(function ($type) {
                            return [
                                'name' => ucfirst(str_replace('_', ' ', $type->value)),
                                'value' => $type->value,
                            ];
                        }, LeaderboardType::cases()),
                    ],
                ],
            ],
            [
                'name' => 'match-report',
                'description' => 'Display a match report with scoreboard and achievements',
                'type' => 1, // CHAT_INPUT
                'options' => [
                    [
                        'name' => 'id',
                        'description' => 'The match ID to display',
                        'type' => 4, // INTEGER
                        'required' => true,
                    ],
                ],
            ],
        ];

        foreach ($commands as $command) {
            $this->info("Registering command: /{$command['name']}");

            try {
                $response = Http::withHeaders([
                    'Authorization' => "Bot {$botToken}",
                    'Content-Type' => 'application/json',
                ])->post($url, $command);

                if ($response->successful()) {
                    $this->info("✓ Successfully registered /{$command['name']}");
                    $this->line("  Description: {$command['description']}");
                } else {
                    $this->error("✗ Failed to register /{$command['name']}");
                    $this->error("  Status: {$response->status()}");
                    $this->error("  Response: {$response->body()}");
                    Log::error('Failed to register Discord command', [
                        'command' => $command['name'],
                        'status' => $response->status(),
                        'response' => $response->body(),
                    ]);
                }
            } catch (\Exception $e) {
                $this->error("✗ Error registering /{$command['name']}: {$e->getMessage()}");
                Log::error('Exception while registering Discord command', [
                    'command' => $command['name'],
                    'error' => $e->getMessage(),
                ]);

                return Command::FAILURE;
            }
        }

        $this->newLine();
        $this->info('Command registration complete!');
        $this->line('Note: Global commands may take up to 1 hour to propagate.');
        $this->line('Guild commands are available immediately.');

        return Command::SUCCESS;
    }
}
