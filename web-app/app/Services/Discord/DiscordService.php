<?php

namespace App\Services\Discord;

use App\Exceptions\DiscordAPIConnectorException;
use App\Models\Clan;
use App\Models\User;
use App\Services\Clans\ClanLeaderboardService;
use App\Services\Clans\ClanService;
use App\Services\Discord\Commands\LeaderboardCommand;
use App\Services\Discord\Commands\MatchReportCommand;
use App\Services\Discord\Commands\MembersCommand;
use App\Services\Discord\Commands\SetupCommand;
use App\Services\Discord\Commands\UnlinkClanCommand;
use App\Services\Integrations\Discord\DiscordRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DiscordService
{
    private const COMMAND_MAP = [
        'setup' => SetupCommand::class,
        'unlink-clan' => UnlinkClanCommand::class,
        'members' => MembersCommand::class,
        'leaderboard' => LeaderboardCommand::class,
        'match-report' => MatchReportCommand::class,
    ];

    public function __construct(
        private readonly ClanService $clanService,
        private readonly ClanLeaderboardService $leaderboardService,
        private readonly DiscordRepository $discordRepository
    ) {}

    /**
     * Handle Discord interaction
     *
     * @return array Discord interaction response
     */
    public function handleInteraction(array $payload): array
    {
        $type = $payload['type'] ?? null;

        Log::info('Discord interaction received', ['type' => $type]);

        return match ($type) {
            1 => $this->handlePing($payload),
            2 => $this->handleApplicationCommand($payload),
            3 => $this->handleMessageComponent($payload),
            5 => $this->handleModalSubmit($payload),
            default => ['type' => 1],
        };
    }

    /**
     * Handle PING interaction
     */
    private function handlePing(array $payload): array
    {
        return ['type' => 1];
    }

    /**
     * Handle bot installation/guild join
     * This is called when the bot is added to a Discord server
     *
     * @param  string  $guildId  Discord guild ID
     * @param  string  $guildName  Discord guild name
     * @param  string  $installerDiscordId  Discord user ID of the installer
     * @param  string|null  $clanName  Optional clan name (from user input)
     * @param  string|null  $clanTag  Optional clan tag (from user input)
     * @return array Discord interaction response
     */
    public function handleBotInstallation(string $guildId, string $guildName, string $installerDiscordId, ?string $clanName = null, ?string $clanTag = null): array
    {
        Log::info('Handling bot installation', [
            'guild_id' => $guildId,
            'guild_name' => $guildName,
            'installer_discord_id' => $installerDiscordId,
        ]);

        $user = User::where('discord_id', $installerDiscordId)
            ->whereNotNull('discord_id')
            ->first();

        if (! $user) {
            Log::warning('Bot installer not found in users or discord_id is null', ['discord_id' => $installerDiscordId]);

            return $this->errorResponse('You must be a Top Frag member with a linked Discord account to install this bot.');
        }

        $existingClan = Clan::findByDiscordGuildId($guildId);
        if ($existingClan) {
            Log::warning('Guild already linked to clan', [
                'guild_id' => $guildId,
                'clan_id' => $existingClan->id,
            ]);

            return $this->errorResponse('This Discord server is already linked to a clan.');
        }

        $unlinkedClans = Clan::where('owned_by', $user->id)
            ->whereNull('discord_guild_id')
            ->get();

        if ($unlinkedClans->isNotEmpty()) {
            $clan = $unlinkedClans->first();
            Log::info('Linking existing clan to Discord guild', [
                'clan_id' => $clan->id,
                'guild_id' => $guildId,
            ]);

            try {
                $this->clanService->linkToDiscordGuild($clan, $guildId, $user);

                $this->addDiscordGuildMembersToClan($clan, $guildId);

                return $this->showChannelSelectionMenu($guildId, $clan);
            } catch (\Exception $e) {
                Log::error('Failed to link clan to Discord guild', [
                    'clan_id' => $clan->id,
                    'guild_id' => $guildId,
                    'error' => $e->getMessage(),
                ]);

                return $this->errorResponse('Failed to link clan: '.$e->getMessage());
            }
        }

        if (! $clanName || ! $clanTag) {
            return $this->errorResponse('Clan name and tag are required when creating a new clan. Please provide both: `/setup name:YourClanName tag:YOUR`');
        }

        Log::info('Creating new clan from Discord guild', [
            'guild_id' => $guildId,
            'guild_name' => $guildName,
            'clan_name' => $clanName,
            'clan_tag' => $clanTag,
            'user_id' => $user->id,
        ]);

        try {
            $clan = $this->clanService->createFromDiscordGuild($user, $guildId, $clanName, $clanTag);

            $this->addDiscordGuildMembersToClan($clan, $guildId);

            return $this->showChannelSelectionMenu($guildId, $clan);
        } catch (\Exception $e) {
            Log::error('Failed to create clan from Discord guild', [
                'guild_id' => $guildId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to create clan: '.$e->getMessage());
        }
    }

    /**
     * Handle application command (slash commands)
     */
    private function handleApplicationCommand(array $payload): array
    {
        $commandName = $payload['data']['name'] ?? null;

        Log::info('Discord application command received', [
            'command' => $commandName,
            'guild_id' => $payload['guild_id'] ?? null,
            'data_keys' => array_keys($payload['data'] ?? []),
            'has_options' => isset($payload['data']['options']),
            'options' => $payload['data']['options'] ?? [],
        ]);

        $commandClass = self::COMMAND_MAP[$commandName] ?? null;

        if (! $commandClass) {
            return $this->errorResponse('Unknown command. Use `/setup` to link this Discord server to your Top Frag clan.');
        }

        try {
            $command = app($commandClass);

            return $command->execute($payload);
        } catch (\Exception $e) {
            Log::error('Failed to execute Discord command', [
                'command' => $commandName,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('An error occurred while processing the command.');
        }
    }

    /**
     * Handle MESSAGE_COMPONENT interactions (select menus, buttons)
     */
    private function handleMessageComponent(array $payload): array
    {
        $customId = $payload['data']['custom_id'] ?? null;
        $values = $payload['data']['values'] ?? [];

        Log::info('Discord message component received', [
            'custom_id' => $customId,
            'values' => $values,
            'guild_id' => $payload['guild_id'] ?? null,
        ]);

        // Route setup-related components to SetupCommand
        if (in_array($customId, ['setup_option', 'link_clan_select'], true)) {
            try {
                $setupCommand = app(SetupCommand::class);
                if ($customId === 'setup_option' && ! empty($values)) {
                    return $setupCommand->handleSetupOption($payload, $values[0]);
                }
                if ($customId === 'link_clan_select' && ! empty($values)) {
                    return $setupCommand->handleLinkClanSelection($payload, $values[0]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to handle setup component', [
                    'custom_id' => $customId,
                    'error' => $e->getMessage(),
                ]);

                return $this->errorResponse('An error occurred while processing the interaction.');
            }
        }

        if ($customId === 'select_match_report_channel' && ! empty($values)) {
            $selectedChannelId = $values[0];

            return $this->handleChannelSelection($payload, $selectedChannelId);
        }

        return $this->errorResponse('Unknown interaction.');
    }

    /**
     * Handle MODAL_SUBMIT interactions (form submissions)
     */
    private function handleModalSubmit(array $payload): array
    {
        $customId = $payload['data']['custom_id'] ?? null;
        $components = $payload['data']['components'] ?? [];

        Log::info('Discord modal submit received', [
            'custom_id' => $customId,
            'guild_id' => $payload['guild_id'] ?? null,
        ]);

        if ($customId === 'create_clan_modal') {
            try {
                $setupCommand = app(SetupCommand::class);

                return $setupCommand->handleCreateClanModal($payload, $components);
            } catch (\Exception $e) {
                Log::error('Failed to handle create clan modal', [
                    'error' => $e->getMessage(),
                ]);

                return $this->errorResponse('An error occurred while processing the form.');
            }
        }

        return $this->errorResponse('Unknown modal.');
    }

    /**
     * Validate clan name
     *
     * @return string|null Error message if invalid, null if valid
     */
    public function validateClanName(string $name): ?string
    {
        $name = trim($name);

        if (empty($name)) {
            return 'Clan name is required.';
        }

        if (strlen($name) > 255) {
            return 'Clan name cannot exceed 255 characters.';
        }

        if (! preg_match('/^[a-zA-Z0-9]+$/', $name)) {
            return 'Clan name must contain only letters and numbers (no spaces or special characters).';
        }

        if (Clan::where('name', $name)->exists()) {
            return 'A clan with this name already exists.';
        }

        return null;
    }

    /**
     * Validate clan tag
     *
     * @return string|null Error message if invalid, null if valid
     */
    public function validateClanTag(string $tag): ?string
    {
        $tag = trim($tag);

        if (empty($tag)) {
            return 'Clan tag is required.';
        }

        if (strlen($tag) > 4) {
            return 'Clan tag cannot exceed 4 characters.';
        }

        if (! preg_match('/^[a-zA-Z0-9]+$/', $tag)) {
            return 'Clan tag must contain only letters and numbers.';
        }

        if (Clan::where('tag', $tag)->exists()) {
            return 'A clan with this tag already exists.';
        }

        return null;
    }

    /**
     * Create success response for Discord interaction
     */
    public function successResponse(string $message): array
    {
        return [
            'type' => 4, // CHANNEL_MESSAGE_WITH_SOURCE
            'data' => [
                'content' => $message,
                'flags' => 64, // EPHEMERAL - only visible to the user who triggered the interaction
            ],
        ];
    }

    /**
     * Fetch Discord guild members and automatically add those with linked Top Frag accounts to the clan
     */
    public function addDiscordGuildMembersToClan(Clan $clan, string $guildId): void
    {
        try {
            $discordUserIds = $this->discordRepository->getGuildMembers($guildId);

            if (empty($discordUserIds)) {
                Log::info('No Discord guild members found or failed to fetch', ['guild_id' => $guildId]);

                return;
            }

            Log::info('Fetched Discord guild members', [
                'guild_id' => $guildId,
                'member_count' => count($discordUserIds),
            ]);

            $topFragUsers = User::whereIn('discord_id', $discordUserIds)
                ->whereNotNull('discord_id')
                ->get();

            Log::info('Found Top Frag users with linked Discord accounts', [
                'guild_id' => $guildId,
                'linked_users_count' => $topFragUsers->count(),
            ]);

            $addedCount = 0;
            foreach ($topFragUsers as $user) {
                if (! $clan->isMember($user)) {
                    try {
                        $this->clanService->addMember($clan, $user);
                        $addedCount++;
                        Log::debug('Added Discord guild member to clan', [
                            'clan_id' => $clan->id,
                            'user_id' => $user->id,
                            'discord_id' => $user->discord_id,
                        ]);
                    } catch (\Exception $e) {
                        Log::warning('Failed to add user to clan', [
                            'clan_id' => $clan->id,
                            'user_id' => $user->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            Log::info('Completed auto-adding Discord guild members to clan', [
                'clan_id' => $clan->id,
                'guild_id' => $guildId,
                'added_count' => $addedCount,
                'total_linked_users' => $topFragUsers->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to auto-add Discord guild members to clan', [
                'clan_id' => $clan->id,
                'guild_id' => $guildId,
                'error' => $e->getMessage(),
            ]);
        } catch (DiscordAPIConnectorException $e) {
            Log::error('Discord API error while auto-adding guild members to clan', [
                'clan_id' => $clan->id,
                'guild_id' => $guildId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Show channel selection menu for match reports
     */
    public function showChannelSelectionMenu(string $guildId, \App\Models\Clan $clan, bool $updateMessage = false): array
    {
        try {
            $channels = $this->discordRepository->getGuildChannels($guildId);
        } catch (DiscordAPIConnectorException $e) {
            Log::warning('Failed to fetch Discord guild channels', [
                'guild_id' => $guildId,
                'error' => $e->getMessage(),
            ]);

            return $this->successResponse('Your clan "'.$clan->name.'" has been linked! (Channel selection skipped - failed to fetch channels)');
        }

        if (empty($channels)) {
            Log::warning('No channels found for guild', ['guild_id' => $guildId]);

            return $this->successResponse('Your clan "'.$clan->name.'" has been linked! (No channels found to select)');
        }

        $options = [];
        foreach (array_slice($channels, 0, 25) as $channel) {
            $channelName = $this->sanitizeUtf8($channel['name'] ?? 'Unknown');
            $channelType = $channel['type'] ?? 0;

            if ($channelType === 0 || $channelType === 15) {
                $options[] = [
                    'label' => mb_substr($channelName, 0, 100), // Discord limit is 100 chars
                    'value' => $channel['id'],
                    'description' => $channelType === 15 ? 'Forum Channel' : 'Text Channel',
                ];
            }
        }

        if (empty($options)) {
            return $this->successResponse('Your clan "'.$clan->name.'" has been linked! (No text channels found to select)');
        }

        $content = '✅ Your clan "'.$clan->name.'" has been linked to this Discord server!'."\n\n";
        $content .= '**Select a channel for match reports:**'."\n";
        $content .= 'Match reports will be automatically posted to the selected channel when matches finish processing.';

        $responseType = $updateMessage ? 7 : 4; // UPDATE_MESSAGE or CHANNEL_MESSAGE_WITH_SOURCE

        return [
            'type' => $responseType,
            'data' => [
                'content' => $content,
                'components' => [
                    [
                        'type' => 1, // ACTION_ROW
                        'components' => [
                            [
                                'type' => 3, // SELECT_MENU
                                'custom_id' => 'select_match_report_channel',
                                'placeholder' => 'Select a channel...',
                                'options' => $options,
                            ],
                        ],
                    ],
                ],
                'flags' => 64, // EPHEMERAL
            ],
        ];
    }

    /**
     * Handle channel selection for match reports
     */
    private function handleChannelSelection(array $payload, string $channelId): array
    {
        $guildId = $payload['guild_id'] ?? null;

        if (! $guildId) {
            return $this->errorResponse('Missing guild ID.');
        }

        $clan = Clan::findByDiscordGuildId($guildId);

        if (! $clan) {
            return $this->errorResponse('Clan not found for this Discord server.');
        }

        try {
            $clan->discord_channel_id = $channelId;
            $clan->save();

            Log::info('Match report channel configured', [
                'clan_id' => $clan->id,
                'channel_id' => $channelId,
            ]);

            $channelName = 'the selected channel';
            try {
                $channelData = $this->discordRepository->getChannel($channelId);
                $channelName = $channelData['name'] ?? $channelName;
            } catch (DiscordAPIConnectorException $e) {
                Log::warning('Failed to fetch channel name', [
                    'channel_id' => $channelId,
                    'error' => $e->getMessage(),
                ]);
            }

            return [
                'type' => 7, // UPDATE_MESSAGE
                'data' => [
                    'content' => '✅ Setup complete! Match reports will be posted to #'.$this->sanitizeUtf8($channelName).' when matches finish processing.',
                    'components' => [], // Remove the select menu
                    'flags' => 64, // EPHEMERAL
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to save channel selection', [
                'clan_id' => $clan->id,
                'channel_id' => $channelId,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to save channel selection: '.$e->getMessage());
        }
    }

    /**
     * Sanitize string to ensure valid UTF-8 encoding
     */
    public function sanitizeUtf8(?string $string): string
    {
        if ($string === null) {
            return '';
        }

        $string = mb_convert_encoding($string, 'UTF-8', 'UTF-8');

        $string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $string);

        if (function_exists('iconv')) {
            $string = @iconv('UTF-8', 'UTF-8//IGNORE', $string);
        }

        return $string ?: '';
    }

    /**
     * Format match report as Discord embed
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $achievements
     */
    public function formatMatchReportEmbed(\App\Models\GameMatch $match, $achievements, ?\App\Models\Clan $clan = null): array
    {
        $title = "Match Report #{$match->id}";
        $mapName = $this->sanitizeUtf8($match->map);
        $description = "**Map:** {$mapName}\n";
        $description .= "**Score:** {$match->winning_team_score} - {$match->losing_team_score}\n";
        if ($match->match_start_time) {
            $description .= "**Date:** {$match->match_start_time->format('M j, Y g:i A')}\n";
        }

        $fields = [];

        if ($achievements->isNotEmpty()) {
            $achievementText = $this->formatAchievements($achievements, $clan);
            if ($achievementText) {
                $fields[] = [
                    'name' => '⠀', // Invisible character for spacing
                    'value' => '⠀',
                    'inline' => false,
                ];
                $fields[] = [
                    'name' => '🏆 Achievements',
                    'value' => $this->sanitizeUtf8($achievementText),
                    'inline' => false,
                ];
            }
        }

        $fields[] = [
            'name' => '⠀', // Invisible character for spacing
            'value' => '⠀',
            'inline' => false,
        ];
        $scoreboardFields = $this->formatScoreboardFields($match, $clan);
        foreach ($scoreboardFields as &$field) {
            if (isset($field['value'])) {
                $field['value'] = $this->sanitizeUtf8($field['value']);
            }
            if (isset($field['name'])) {
                $field['name'] = $this->sanitizeUtf8($field['name']);
            }
        }
        unset($field);
        $fields = array_merge($fields, $scoreboardFields);

        $matchUrl = url("/matches/{$match->id}/match-details");

        $embed = [
            'title' => $this->sanitizeUtf8($title),
            'url' => $matchUrl,
            'description' => $this->sanitizeUtf8($description),
            'fields' => $fields,
            'color' => 0x5865F2, // Discord blurple color
        ];

        if ($clan) {
            $embed['footer'] = [
                'text' => $this->sanitizeUtf8($clan->name),
            ];
        }

        return $embed;
    }

    /**
     * Format achievements for Discord embed
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $achievements
     */
    private function formatAchievements($achievements, ?\App\Models\Clan $clan = null): string
    {
        if ($clan) {
            $clanMemberUserIds = $clan->members()->pluck('user_id');
            $clanMemberSteamIds = \Illuminate\Support\Facades\DB::table('users')
                ->whereIn('id', $clanMemberUserIds)
                ->whereNotNull('steam_id')
                ->pluck('steam_id')
                ->toArray();

            $clanMemberPlayerIds = \App\Models\Player::whereIn('steam_id', $clanMemberSteamIds)
                ->pluck('id')
                ->toArray();

            $achievements = $achievements->filter(function ($achievement) use ($clanMemberPlayerIds) {
                return in_array($achievement->player_id, $clanMemberPlayerIds);
            });
        }

        if ($achievements->isEmpty()) {
            return '';
        }

        $achievementList = [];
        foreach ($achievements as $achievement) {
            $playerName = $this->sanitizeUtf8($achievement->player->name ?? 'Unknown');
            $achievementName = ucfirst(str_replace('_', ' ', $achievement->award_name->value));
            $achievementList[] = "**{$playerName}** - {$achievementName}";
        }

        return implode("\n", $achievementList);
    }

    /**
     * Get visual width of a string (accounts for Unicode full-width characters)
     */
    private function getStringWidth(string $string): int
    {
        $width = 0;
        $length = mb_strlen($string, 'UTF-8');

        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($string, $i, 1, 'UTF-8');
            $charWidth = mb_strwidth($char, 'UTF-8');
            $width += $charWidth;
        }

        return $width;
    }

    /**
     * Pad string to a specific visual width
     */
    private function padToWidth(string $string, int $width, string $padString = ' ', int $padType = STR_PAD_RIGHT): string
    {
        $currentWidth = $this->getStringWidth($string);
        $paddingNeeded = $width - $currentWidth;

        if ($paddingNeeded <= 0) {
            return $string;
        }

        if ($padType === STR_PAD_RIGHT) {
            return $string.str_repeat($padString, $paddingNeeded);
        } elseif ($padType === STR_PAD_LEFT) {
            return str_repeat($padString, $paddingNeeded).$string;
        } else {
            $left = (int) floor($paddingNeeded / 2);
            $right = $paddingNeeded - $left;

            return str_repeat($padString, $left).$string.str_repeat($padString, $right);
        }
    }

    /**
     * Format scoreboard as Discord embed fields with teams side by side
     */
    private function formatScoreboardFields(\App\Models\GameMatch $match, ?\App\Models\Clan $clan = null): array
    {
        $playerMatchEvents = $match->playerMatchEvents;

        if ($clan) {
            $clanMemberUserIds = $clan->members()->pluck('user_id');
            $clanMemberSteamIds = \Illuminate\Support\Facades\DB::table('users')
                ->whereIn('id', $clanMemberUserIds)
                ->whereNotNull('steam_id')
                ->pluck('steam_id')
                ->toArray();

            $playerMatchEvents = $playerMatchEvents->filter(function ($event) use ($clanMemberSteamIds) {
                return in_array($event->player_steam_id, $clanMemberSteamIds);
            });
        }

        if ($playerMatchEvents->isEmpty()) {
            return [
                [
                    'name' => '📊 Scoreboard',
                    'value' => 'No player data available.',
                    'inline' => false,
                ],
            ];
        }

        $teamA = [];
        $teamB = [];

        foreach ($playerMatchEvents as $event) {
            $player = $match->players->where('steam_id', $event->player_steam_id)->first();
            $teamValue = 'A';
            if ($player && $player->pivot) {
                $team = $player->pivot->team;
                $teamValue = is_object($team) && method_exists($team, 'value') ? $team->value : (string) $team;
            }

            $playerName = $this->sanitizeUtf8($event->player->name ?? 'Unknown');
            $kd = $this->calculateKillDeathRatio($event->kills, $event->deaths);
            $fkDiff = $event->first_kills - $event->first_deaths;
            $adr = round($event->adr);

            $playerData = [
                'name' => $playerName,
                'kd' => $kd,
                'kills' => $event->kills,
                'deaths' => $event->deaths,
                'first_kills' => $event->first_kills,
                'first_deaths' => $event->first_deaths,
                'fkDiff' => $fkDiff,
                'adr' => $adr,
            ];

            if ($teamValue === 'A') {
                $teamA[] = $playerData;
            } else {
                $teamB[] = $playerData;
            }
        }

        usort($teamA, fn ($a, $b) => $b['kills'] <=> $a['kills']);
        usort($teamB, fn ($a, $b) => $b['kills'] <=> $a['kills']);

        $formatTeamList = function ($team, $teamName) {
            $lines = [];
            $lines[] = "**{$teamName}**";
            $lines[] = ''; // Blank line after header

            foreach ($team as $index => $player) {
                $playerName = $this->sanitizeUtf8($player['name']);
                $fkFormatted = "+{$player['first_kills']}/-{$player['first_deaths']}";

                $lines[] = $playerName;
                $lines[] = "K/D: {$player['kills']}/{$player['deaths']} | FK {$fkFormatted} | ADR: {$player['adr']}";

                if ($index < count($team) - 1) {
                    $lines[] = '';
                }
            }

            return implode("\n", $lines);
        };

        $teamAText = $formatTeamList($teamA, 'Team A');
        $teamBText = $formatTeamList($teamB, 'Team B');

        $fields = [
            [
                'name' => '📊 Scoreboard',
                'value' => $teamAText,
                'inline' => false,
            ],
            [
                'name' => '⠀', // Invisible character for spacing
                'value' => $teamBText,
                'inline' => false,
            ],
        ];

        return $fields;
    }

    /**
     * Calculate kill/death ratio
     */
    private function calculateKillDeathRatio(int $kills, int $deaths): float
    {
        if ($deaths === 0) {
            return (float) $kills;
        }

        return round($kills / $deaths, 2);
    }

    /**
     * Send match report to Discord channel
     */
    public function sendMatchReportToDiscord(\App\Models\GameMatch $match, \App\Models\Clan $clan): void
    {
        if (! $clan->discord_guild_id || ! $clan->discord_channel_id) {
            Log::info('Clan not configured for Discord notifications', [
                'clan_id' => $clan->id,
                'has_guild_id' => ! empty($clan->discord_guild_id),
                'has_channel_id' => ! empty($clan->discord_channel_id),
            ]);

            return;
        }

        if (! $match->relationLoaded('playerMatchEvents')) {
            $match->load(['playerMatchEvents.player', 'players']);
        }

        $clanMemberUserIds = $clan->members()->pluck('user_id');
        $clanMemberSteamIds = \Illuminate\Support\Facades\DB::table('users')
            ->whereIn('id', $clanMemberUserIds)
            ->whereNotNull('steam_id')
            ->pluck('steam_id')
            ->toArray();

        $clanMemberPlayerIds = \App\Models\Player::whereIn('steam_id', $clanMemberSteamIds)
            ->pluck('id')
            ->toArray();

        $achievements = \App\Models\Achievement::where('match_id', $match->id)
            ->whereIn('player_id', $clanMemberPlayerIds)
            ->with('player')
            ->get();

        $embed = $this->formatMatchReportEmbed($match, $achievements, $clan);

        try {
            $this->discordRepository->sendMessage($clan->discord_channel_id, [
                'embeds' => [$embed],
            ]);

            Log::info('Match report sent to Discord', [
                'match_id' => $match->id,
                'clan_id' => $clan->id,
                'channel_id' => $clan->discord_channel_id,
            ]);
        } catch (DiscordAPIConnectorException $e) {
            Log::error('Failed to send match report to Discord', [
                'match_id' => $match->id,
                'clan_id' => $clan->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send leaderboard to Discord channel
     */
    public function sendLeaderboardToDiscord(Clan $clan, string $leaderboardType, Carbon $startDate, Carbon $endDate): void
    {
        if (! $clan->discord_guild_id || ! $clan->discord_channel_id) {
            Log::info('Clan not configured for Discord notifications', [
                'clan_id' => $clan->id,
                'has_guild_id' => ! empty($clan->discord_guild_id),
                'has_channel_id' => ! empty($clan->discord_channel_id),
            ]);

            return;
        }

        $leaderboard = $this->leaderboardService->getLeaderboard($clan, $leaderboardType, $startDate, $endDate);

        if ($leaderboard->isEmpty()) {
            Log::info('Leaderboard is empty, skipping Discord notification', [
                'clan_id' => $clan->id,
                'leaderboard_type' => $leaderboardType,
            ]);

            return;
        }

        $typeLabel = ucfirst(str_replace('_', ' ', $leaderboardType));
        $typeEmoji = $this->getLeaderboardTypeEmoji($leaderboardType);
        $titlePrefix = $typeEmoji ? "{$typeEmoji} " : '';

        $fields = [];
        $topEntries = $leaderboard->take(10);

        foreach ($topEntries as $entry) {
            $user = $entry->user;
            $userName = $user ? ($user->name ?? $user->steam_persona_name ?? 'Unknown') : 'Unknown';
            $value = number_format((float) $entry->value, 2);

            $trophyEmote = match ($entry->position) {
                1 => '🥇',
                2 => '🥈',
                3 => '🥉',
                default => '',
            };

            $fields[] = [
                'name' => "{$trophyEmote} #{$entry->position} {$userName}",
                'value' => $value,
                'inline' => false,
            ];
        }

        $dateRange = $startDate->format('M j').' - '.$endDate->format('M j, Y');

        $embed = [
            'title' => "{$titlePrefix}Weekly {$typeLabel} Leaderboard",
            'description' => "Period: {$dateRange}",
            'fields' => $fields,
            'footer' => [
                'text' => $this->sanitizeUtf8($clan->name),
            ],
            'color' => 0x5865F2, // Discord blurple color
        ];

        try {
            $this->discordRepository->sendMessage($clan->discord_channel_id, [
                'embeds' => [$embed],
            ]);

            Log::info('Leaderboard sent to Discord', [
                'clan_id' => $clan->id,
                'leaderboard_type' => $leaderboardType,
                'channel_id' => $clan->discord_channel_id,
            ]);
        } catch (DiscordAPIConnectorException $e) {
            Log::error('Failed to send leaderboard to Discord', [
                'clan_id' => $clan->id,
                'leaderboard_type' => $leaderboardType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get emoji for leaderboard type
     */
    public function getLeaderboardTypeEmoji(string $leaderboardType): ?string
    {
        return match ($leaderboardType) {
            'fragger' => '🔫',
            'support' => '🛡️',
            'opener' => '⚡',
            'closer' => '🔒',
            'aim' => '🎯',
            'impact' => '💥',
            'round_swing' => '🔄',
            default => null,
        };
    }

    /**
     * Create error response for Discord interaction
     */
    public function errorResponse(string $message): array
    {
        return [
            'type' => 4, // CHANNEL_MESSAGE_WITH_SOURCE
            'data' => [
                'content' => '❌ '.$message,
                'flags' => 64, // EPHEMERAL
            ],
        ];
    }
}
