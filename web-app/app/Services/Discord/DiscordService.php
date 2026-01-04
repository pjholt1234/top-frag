<?php

namespace App\Services\Discord;

use App\Enums\LeaderboardType;
use App\Models\Clan;
use App\Models\User;
use App\Services\Clans\ClanLeaderboardService;
use App\Services\Clans\ClanService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordService
{
    public function __construct(
        private readonly ClanService $clanService,
        private readonly ClanLeaderboardService $leaderboardService
    ) {}

    /**
     * Handle Discord interaction
     *
     * @param  array  $payload
     * @return array Discord interaction response
     */
    public function handleInteraction(array $payload): array
    {
        $type = $payload['type'] ?? null;

        Log::info('Discord interaction received', ['type' => $type]);

        // Handle PING (required for Discord verification)
        if ($type === 1) {
            return ['type' => 1]; // PONG response
        }

        // Handle APPLICATION_COMMAND (slash commands)
        if ($type === 2) {
            return $this->handleApplicationCommand($payload);
        }

        // Handle MESSAGE_COMPONENT (button/select menu interactions)
        if ($type === 3) {
            return $this->handleMessageComponent($payload);
        }

        // Handle MODAL_SUBMIT (modal form submissions)
        if ($type === 5) {
            return $this->handleModalSubmit($payload);
        }

        // Default acknowledgment
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

        // Validate installer requirements
        $user = User::where('discord_id', $installerDiscordId)
            ->whereNotNull('discord_id')
            ->first();

        if (! $user) {
            Log::warning('Bot installer not found in users or discord_id is null', ['discord_id' => $installerDiscordId]);

            return $this->errorResponse('You must be a Top Frag member with a linked Discord account to install this bot.');
        }

        // Check if clan already exists for this guild
        $existingClan = Clan::findByDiscordGuildId($guildId);
        if ($existingClan) {
            Log::warning('Guild already linked to clan', [
                'guild_id' => $guildId,
                'clan_id' => $existingClan->id,
            ]);

            return $this->errorResponse('This Discord server is already linked to a clan.');
        }

        // Find installer's unlinked clans
        $unlinkedClans = Clan::where('owned_by', $user->id)
            ->whereNull('discord_guild_id')
            ->get();

        if ($unlinkedClans->isNotEmpty()) {
            // Link the first unlinked clan (name/tag not needed for linking)
            $clan = $unlinkedClans->first();
            Log::info('Linking existing clan to Discord guild', [
                'clan_id' => $clan->id,
                'guild_id' => $guildId,
            ]);

            try {
                $this->clanService->linkToDiscordGuild($clan, $guildId, $user);

                // Automatically add all Discord server members with linked Top Frag accounts
                $this->addDiscordGuildMembersToClan($clan, $guildId);

                // Show channel selection menu
                return $this->showChannelSelectionMenu($guildId, $clan);
            } catch (\Exception $e) {
                Log::error('Failed to link clan to Discord guild', [
                    'clan_id' => $clan->id,
                    'guild_id' => $guildId,
                    'error' => $e->getMessage(),
                ]);

                return $this->errorResponse('Failed to link clan: ' . $e->getMessage());
            }
        }

        // Create new clan - name and tag are required
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

            // Automatically add all Discord server members with linked Top Frag accounts
            $this->addDiscordGuildMembersToClan($clan, $guildId);

            // Show channel selection menu
            return $this->showChannelSelectionMenu($guildId, $clan);
        } catch (\Exception $e) {
            Log::error('Failed to create clan from Discord guild', [
                'guild_id' => $guildId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to create clan: ' . $e->getMessage());
        }
    }

    /**
     * Handle application command (slash commands)
     *
     * @param  array  $payload
     * @return array
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

        if ($commandName === 'setup') {
            return $this->handleSetupCommand($payload);
        }

        if ($commandName === 'unlink-clan') {
            return $this->handleUnlinkClanCommand($payload);
        }

        if ($commandName === 'members') {
            return $this->handleMembersCommand($payload);
        }

        if ($commandName === 'leaderboard') {
            return $this->handleLeaderboardCommand($payload);
        }

        if ($commandName === 'match-report') {
            return $this->handleMatchReportCommand($payload);
        }

        // Unknown command
        return [
            'type' => 4, // CHANNEL_MESSAGE_WITH_SOURCE
            'data' => [
                'content' => '❌ Unknown command. Use `/setup` to link this Discord server to your Top Frag clan.',
                'flags' => 64, // EPHEMERAL
            ],
        ];
    }

    /**
     * Handle /setup slash command - shows initial options
     *
     * @param  array  $payload
     * @return array
     */
    private function handleSetupCommand(array $payload): array
    {
        // Extract guild and user info from the interaction
        $guildId = $payload['guild_id'] ?? null;
        $guildName = $payload['guild']['name'] ?? 'Unknown Guild';

        // User info can be in 'member.user.id' (guild) or 'user.id' (DM)
        $installerDiscordId = $payload['member']['user']['id'] ?? $payload['user']['id'] ?? null;

        if (! $guildId || ! $installerDiscordId) {
            Log::warning('Setup command missing required data', [
                'has_guild_id' => ! empty($guildId),
                'has_installer_id' => ! empty($installerDiscordId),
            ]);

            return $this->errorResponse('This command can only be used in a Discord server.');
        }

        // Validate installer requirements
        $user = User::where('discord_id', $installerDiscordId)
            ->whereNotNull('discord_id')
            ->first();

        if (! $user) {
            return $this->errorResponse('You must be a Top Frag member with a linked Discord account to use this command.');
        }

        // Check if guild is already linked
        $existingClan = Clan::findByDiscordGuildId($guildId);
        if ($existingClan) {
            return $this->errorResponse('This Discord server is already linked to clan "' . $existingClan->name . '". Use `/unlink-clan` to unlink it first.');
        }

        // Show select menu with options: Create new clan or Link existing clan
        return [
            'type' => 4, // CHANNEL_MESSAGE_WITH_SOURCE
            'data' => [
                'content' => '**Setup Top Frag Clan**\n\nChoose an option:',
                'components' => [
                    [
                        'type' => 1, // ACTION_ROW
                        'components' => [
                            [
                                'type' => 3, // SELECT_MENU
                                'custom_id' => 'setup_option',
                                'placeholder' => 'Select an option...',
                                'options' => [
                                    [
                                        'label' => 'Create new clan',
                                        'value' => 'create_new',
                                        'description' => 'Create a new clan and link it to this server',
                                        'emoji' => ['name' => '➕'],
                                    ],
                                    [
                                        'label' => 'Link existing clan',
                                        'value' => 'link_existing',
                                        'description' => 'Link one of your existing clans to this server',
                                        'emoji' => ['name' => '🔗'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'flags' => 64, // EPHEMERAL
            ],
        ];
    }

    /**
     * Handle MESSAGE_COMPONENT interactions (select menus, buttons)
     *
     * @param  array  $payload
     * @return array
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

        // Handle setup option selection
        if ($customId === 'setup_option' && ! empty($values)) {
            $selectedOption = $values[0];
            return $this->handleSetupOption($payload, $selectedOption);
        }

        // Handle clan selection for linking
        if ($customId === 'link_clan_select' && ! empty($values)) {
            $selectedClanTag = $values[0];
            return $this->handleLinkClanSelection($payload, $selectedClanTag);
        }

        // Handle channel selection for match reports
        if ($customId === 'select_match_report_channel' && ! empty($values)) {
            $selectedChannelId = $values[0];
            return $this->handleChannelSelection($payload, $selectedChannelId);
        }

        return $this->errorResponse('Unknown interaction.');
    }

    /**
     * Handle setup option selection (create new or link existing)
     *
     * @param  array  $payload
     * @param  string  $option
     * @return array
     */
    private function handleSetupOption(array $payload, string $option): array
    {
        $guildId = $payload['guild_id'] ?? null;
        $installerDiscordId = $payload['member']['user']['id'] ?? $payload['user']['id'] ?? null;

        if (! $guildId || ! $installerDiscordId) {
            return $this->errorResponse('Missing required data.');
        }

        $user = User::where('discord_id', $installerDiscordId)
            ->whereNotNull('discord_id')
            ->first();

        if (! $user) {
            return $this->errorResponse('User not found.');
        }

        if ($option === 'create_new') {
            // Show modal for creating new clan
            return [
                'type' => 9, // MODAL
                'data' => [
                    'title' => 'Create New Clan',
                    'custom_id' => 'create_clan_modal',
                    'components' => [
                        [
                            'type' => 1, // ACTION_ROW
                            'components' => [
                                [
                                    'type' => 4, // TEXT_INPUT
                                    'custom_id' => 'clan_name',
                                    'label' => 'Clan Name',
                                    'style' => 1, // SHORT
                                    'min_length' => 1,
                                    'max_length' => 255,
                                    'placeholder' => 'MyAwesomeClan',
                                    'required' => true,
                                ],
                            ],
                        ],
                        [
                            'type' => 1, // ACTION_ROW
                            'components' => [
                                [
                                    'type' => 4, // TEXT_INPUT
                                    'custom_id' => 'clan_tag',
                                    'label' => 'Clan Tag',
                                    'style' => 1, // SHORT
                                    'min_length' => 1,
                                    'max_length' => 4,
                                    'placeholder' => 'MAC',
                                    'required' => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ];
        }

        if ($option === 'link_existing') {
            // Get user's unlinked clans
            $unlinkedClans = Clan::where('owned_by', $user->id)
                ->whereNull('discord_guild_id')
                ->get();

            if ($unlinkedClans->isEmpty()) {
                return $this->errorResponse('You don\'t have any unlinked clans. Use "Create new clan" instead.');
            }

            // Build select menu options
            $options = [];
            foreach ($unlinkedClans as $clan) {
                $options[] = [
                    'label' => $clan->name . ' - ' . $clan->tag,
                    'value' => $clan->tag, // Use tag as identifier since it's unique
                    'description' => 'Tag: ' . $clan->tag,
                ];
            }

            // Discord select menus support max 25 options
            if (count($options) > 25) {
                $options = array_slice($options, 0, 25);
            }

            return [
                'type' => 4, // CHANNEL_MESSAGE_WITH_SOURCE (for component interactions, we can send new message)
                'data' => [
                    'content' => '**Link Existing Clan**\n\nSelect the clan you want to link to this Discord server:',
                    'components' => [
                        [
                            'type' => 1, // ACTION_ROW
                            'components' => [
                                [
                                    'type' => 3, // SELECT_MENU
                                    'custom_id' => 'link_clan_select',
                                    'placeholder' => 'Select a clan...',
                                    'options' => $options,
                                ],
                            ],
                        ],
                    ],
                    'flags' => 64, // EPHEMERAL
                ],
            ];
        }

        return $this->errorResponse('Unknown option.');
    }

    /**
     * Handle clan selection for linking
     *
     * @param  array  $payload
     * @param  string  $clanTag
     * @return array
     */
    private function handleLinkClanSelection(array $payload, string $clanTag): array
    {
        $guildId = $payload['guild_id'] ?? null;
        $guildName = $payload['guild']['name'] ?? 'Unknown Guild';
        $installerDiscordId = $payload['member']['user']['id'] ?? $payload['user']['id'] ?? null;

        if (! $guildId || ! $installerDiscordId) {
            return $this->errorResponse('Missing required data.');
        }

        $user = User::where('discord_id', $installerDiscordId)
            ->whereNotNull('discord_id')
            ->first();

        if (! $user) {
            return $this->errorResponse('User not found.');
        }

        // Find the clan by tag (owned by user and unlinked)
        $clan = Clan::where('tag', $clanTag)
            ->where('owned_by', $user->id)
            ->whereNull('discord_guild_id')
            ->first();

        if (! $clan) {
            return $this->errorResponse('Clan not found or already linked.');
        }

        try {
            $this->clanService->linkToDiscordGuild($clan, $guildId, $user);

            // Automatically add all Discord server members with linked Top Frag accounts
            $this->addDiscordGuildMembersToClan($clan, $guildId);

            // Show channel selection menu
            return $this->showChannelSelectionMenu($guildId, $clan, true);
        } catch (\Exception $e) {
            Log::error('Failed to link clan', [
                'clan_tag' => $clanTag,
                'guild_id' => $guildId,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to link clan: ' . $e->getMessage());
        }
    }

    /**
     * Handle MODAL_SUBMIT interactions (form submissions)
     *
     * @param  array  $payload
     * @return array
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
            return $this->handleCreateClanModal($payload, $components);
        }

        return $this->errorResponse('Unknown modal.');
    }

    /**
     * Handle create clan modal submission
     *
     * @param  array  $payload
     * @param  array  $components
     * @return array
     */
    private function handleCreateClanModal(array $payload, array $components): array
    {
        $guildId = $payload['guild_id'] ?? null;
        $guildName = $payload['guild']['name'] ?? 'Unknown Guild';
        $installerDiscordId = $payload['member']['user']['id'] ?? $payload['user']['id'] ?? null;

        if (! $guildId || ! $installerDiscordId) {
            return $this->errorResponse('Missing required data.');
        }

        // Extract form values
        $clanName = null;
        $clanTag = null;

        foreach ($components as $row) {
            foreach ($row['components'] ?? [] as $component) {
                if ($component['custom_id'] === 'clan_name') {
                    $clanName = $component['value'] ?? null;
                }
                if ($component['custom_id'] === 'clan_tag') {
                    $clanTag = $component['value'] ?? null;
                }
            }
        }

        // Validate required fields
        if (empty($clanName) || empty($clanTag)) {
            return $this->errorResponse('Clan name and tag are required.');
        }

        // Validate format and uniqueness
        $nameValidation = $this->validateClanName($clanName);
        if ($nameValidation !== null) {
            return $this->errorResponse($nameValidation);
        }

        $tagValidation = $this->validateClanTag($clanTag);
        if ($tagValidation !== null) {
            return $this->errorResponse($tagValidation);
        }

        Log::info('Create clan modal submitted', [
            'guild_id' => $guildId,
            'guild_name' => $guildName,
            'installer_discord_id' => $installerDiscordId,
            'clan_name' => $clanName,
            'clan_tag' => $clanTag,
        ]);

        // Create the clan - handleBotInstallation will return the response
        $response = $this->handleBotInstallation($guildId, $guildName, $installerDiscordId, $clanName, $clanTag);

        // Ensure it's a proper message response (not modal)
        if (isset($response['type']) && $response['type'] === 4) {
            return $response;
        }

        // Fallback to success message
        return $this->successResponse('Clan created successfully!');
    }

    /**
     * Handle /unlink-clan command
     *
     * @param  array  $payload
     * @return array
     */
    private function handleUnlinkClanCommand(array $payload): array
    {
        $guildId = $payload['guild_id'] ?? null;
        $installerDiscordId = $payload['member']['user']['id'] ?? $payload['user']['id'] ?? null;

        if (! $guildId || ! $installerDiscordId) {
            return $this->errorResponse('This command can only be used in a Discord server.');
        }

        $user = User::where('discord_id', $installerDiscordId)
            ->whereNotNull('discord_id')
            ->first();

        if (! $user) {
            return $this->errorResponse('You must be a Top Frag member with a linked Discord account to use this command.');
        }

        // Find the clan linked to this guild
        $clan = Clan::findByDiscordGuildId($guildId);

        if (! $clan) {
            return $this->errorResponse('This Discord server is not linked to any clan.');
        }

        // Verify user is the owner
        if (! $clan->isOwner($user)) {
            return $this->errorResponse('Only the clan owner can unlink the clan from Discord.');
        }

        try {
            $clan->discord_guild_id = null;
            $clan->save();

            Log::info('Clan unlinked from Discord server', [
                'clan_id' => $clan->id,
                'guild_id' => $guildId,
                'user_id' => $user->id,
            ]);

            return $this->successResponse('Clan "' . $clan->name . '" has been unlinked from this Discord server.');
        } catch (\Exception $e) {
            Log::error('Failed to unlink clan', [
                'clan_id' => $clan->id,
                'guild_id' => $guildId,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to unlink clan: ' . $e->getMessage());
        }
    }

    /**
     * Validate clan name
     *
     * @param  string  $name
     * @return string|null Error message if invalid, null if valid
     */
    private function validateClanName(string $name): ?string
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

        // Check if name already exists
        if (Clan::where('name', $name)->exists()) {
            return 'A clan with this name already exists.';
        }

        return null;
    }

    /**
     * Validate clan tag
     *
     * @param  string  $tag
     * @return string|null Error message if invalid, null if valid
     */
    private function validateClanTag(string $tag): ?string
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

        // Check if tag already exists
        if (Clan::where('tag', $tag)->exists()) {
            return 'A clan with this tag already exists.';
        }

        return null;
    }

    /**
     * Create success response for Discord interaction
     *
     * @param  string  $message
     * @return array
     */
    private function successResponse(string $message): array
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
     *
     * @param  Clan  $clan
     * @param  string  $guildId
     * @return void
     */
    private function addDiscordGuildMembersToClan(Clan $clan, string $guildId): void
    {
        $botToken = config('services.discord.bot_token');

        if (! $botToken) {
            Log::warning('Discord bot token not configured, skipping auto-add of guild members');

            return;
        }

        try {
            // Fetch all guild members from Discord API
            // Note: Discord API returns paginated results, we'll fetch up to 1000 members
            $discordUserIds = $this->fetchGuildMembers($guildId, $botToken);

            if (empty($discordUserIds)) {
                Log::info('No Discord guild members found or failed to fetch', ['guild_id' => $guildId]);

                return;
            }

            Log::info('Fetched Discord guild members', [
                'guild_id' => $guildId,
                'member_count' => count($discordUserIds),
            ]);

            // Find Top Frag users with matching Discord IDs
            $topFragUsers = User::whereIn('discord_id', $discordUserIds)
                ->whereNotNull('discord_id')
                ->get();

            Log::info('Found Top Frag users with linked Discord accounts', [
                'guild_id' => $guildId,
                'linked_users_count' => $topFragUsers->count(),
            ]);

            // Add each user to the clan (if not already a member)
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
            // Don't fail the entire setup if auto-adding members fails
            Log::error('Failed to auto-add Discord guild members to clan', [
                'clan_id' => $clan->id,
                'guild_id' => $guildId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Show channel selection menu for match reports
     *
     * @param  string  $guildId
     * @param  \App\Models\Clan  $clan
     * @param  bool  $updateMessage
     * @return array
     */
    private function showChannelSelectionMenu(string $guildId, \App\Models\Clan $clan, bool $updateMessage = false): array
    {
        $botToken = config('services.discord.bot_token');

        if (! $botToken) {
            Log::warning('Discord bot token not configured, skipping channel selection');
            return $this->successResponse('Your clan "' . $clan->name . '" has been linked! (Channel selection skipped - bot token not configured)');
        }

        // Fetch guild channels
        $channels = $this->fetchGuildChannels($guildId, $botToken);

        if (empty($channels)) {
            Log::warning('No channels found for guild', ['guild_id' => $guildId]);
            return $this->successResponse('Your clan "' . $clan->name . '" has been linked! (No channels found to select)');
        }

        // Build select menu options (Discord supports max 25 options)
        $options = [];
        foreach (array_slice($channels, 0, 25) as $channel) {
            $channelName = $this->sanitizeUtf8($channel['name'] ?? 'Unknown');
            $channelType = $channel['type'] ?? 0;

            // Only show text channels (type 0) and forum channels (type 15)
            if ($channelType === 0 || $channelType === 15) {
                $options[] = [
                    'label' => mb_substr($channelName, 0, 100), // Discord limit is 100 chars
                    'value' => $channel['id'],
                    'description' => $channelType === 15 ? 'Forum Channel' : 'Text Channel',
                ];
            }
        }

        if (empty($options)) {
            return $this->successResponse('Your clan "' . $clan->name . '" has been linked! (No text channels found to select)');
        }

        $content = '✅ Your clan "' . $clan->name . '" has been linked to this Discord server!' . "\n\n";
        $content .= '**Select a channel for match reports:**' . "\n";
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
     *
     * @param  array  $payload
     * @param  string  $channelId
     * @return array
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

            // Get channel name for confirmation
            $botToken = config('services.discord.bot_token');
            $channelName = 'the selected channel';
            if ($botToken) {
                try {
                    $response = Http::withHeaders([
                        'Authorization' => "Bot {$botToken}",
                    ])->get("https://discord.com/api/v10/channels/{$channelId}");

                    if ($response->successful()) {
                        $channelData = $response->json();
                        $channelName = $channelData['name'] ?? $channelName;
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to fetch channel name', [
                        'channel_id' => $channelId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return [
                'type' => 7, // UPDATE_MESSAGE
                'data' => [
                    'content' => '✅ Setup complete! Match reports will be posted to #' . $this->sanitizeUtf8($channelName) . ' when matches finish processing.',
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

            return $this->errorResponse('Failed to save channel selection: ' . $e->getMessage());
        }
    }

    /**
     * Fetch guild channels from Discord API
     *
     * @param  string  $guildId
     * @param  string  $botToken
     * @return array Array of channel data
     */
    private function fetchGuildChannels(string $guildId, string $botToken): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bot {$botToken}",
            ])->get("https://discord.com/api/v10/guilds/{$guildId}/channels");

            if (! $response->successful()) {
                Log::error('Failed to fetch Discord guild channels', [
                    'guild_id' => $guildId,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                return [];
            }

            $channels = $response->json();

            // Sort channels by position
            usort($channels, function ($a, $b) {
                $posA = $a['position'] ?? 999;
                $posB = $b['position'] ?? 999;
                return $posA <=> $posB;
            });

            return $channels;
        } catch (\Exception $e) {
            Log::error('Exception while fetching Discord guild channels', [
                'guild_id' => $guildId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Fetch all guild members from Discord API
     *
     * @param  string  $guildId
     * @param  string  $botToken
     * @return array Array of Discord user IDs
     */
    private function fetchGuildMembers(string $guildId, string $botToken): array
    {
        $discordUserIds = [];
        $limit = 1000; // Discord API max limit
        $after = null; // For pagination

        do {
            $url = "https://discord.com/api/v10/guilds/{$guildId}/members?limit={$limit}";
            if ($after) {
                $url .= "&after={$after}";
            }

            try {
                $response = Http::withHeaders([
                    'Authorization' => "Bot {$botToken}",
                ])->get($url);

                if (! $response->successful()) {
                    Log::error('Failed to fetch Discord guild members', [
                        'guild_id' => $guildId,
                        'status' => $response->status(),
                        'response' => $response->body(),
                    ]);

                    break;
                }

                $members = $response->json();

                if (empty($members)) {
                    break;
                }

                // Extract user IDs from members
                foreach ($members as $member) {
                    if (isset($member['user']['id'])) {
                        $discordUserIds[] = $member['user']['id'];
                    }
                }

                // Check if there are more pages
                // Discord returns members in chunks, if we got less than the limit, we're done
                if (count($members) < $limit) {
                    break;
                }

                // Set 'after' to the last member's user ID for pagination
                $lastMember = end($members);
                $after = $lastMember['user']['id'] ?? null;
            } catch (\Exception $e) {
                Log::error('Exception while fetching Discord guild members', [
                    'guild_id' => $guildId,
                    'error' => $e->getMessage(),
                ]);

                break;
            }
        } while ($after !== null && count($discordUserIds) < 10000); // Safety limit

        return array_unique($discordUserIds);
    }

    /**
     * Handle /members command
     *
     * @param  array  $payload
     * @return array
     */
    private function handleMembersCommand(array $payload): array
    {
        $guildId = $payload['guild_id'] ?? null;

        if (! $guildId) {
            return $this->errorResponse('This command can only be used in a Discord server.');
        }

        // Find the clan linked to this guild
        $clan = Clan::findByDiscordGuildId($guildId);

        if (! $clan) {
            return $this->errorResponse('This Discord server is not linked to any clan.');
        }

        // Get all clan members
        $members = $clan->members()->with('user')->get();

        if ($members->isEmpty()) {
            return $this->successResponse('This clan has no members yet.');
        }

        // Format member list
        $memberList = [];
        foreach ($members as $member) {
            $user = $member->user;
            $isTopFragMember = $user && $user->discord_id !== null;
            $status = $isTopFragMember ? '✅' : '❌';
            $userName = $user ? ($user->name ?? $user->steam_persona_name ?? 'Unknown') : 'Unknown';
            $memberList[] = "{$status} {$userName}";
        }

        $content = "**Clan Members ({$clan->name})**\n\n";
        $content .= implode("\n", $memberList);
        $content .= "\n\n✅ = Top Frag member with Discord linked\n❌ = Not a Top Frag member or Discord not linked";

        return [
            'type' => 4, // CHANNEL_MESSAGE_WITH_SOURCE
            'data' => [
                'content' => $content,
                'flags' => 64, // EPHEMERAL
            ],
        ];
    }

    /**
     * Handle /leaderboard command
     *
     * @param  array  $payload
     * @return array
     */
    private function handleLeaderboardCommand(array $payload): array
    {
        $guildId = $payload['guild_id'] ?? null;

        if (! $guildId) {
            return $this->errorResponse('This command can only be used in a Discord server.');
        }

        // Extract leaderboard_type from options
        $options = $payload['data']['options'] ?? [];
        $leaderboardType = null;

        foreach ($options as $option) {
            if ($option['name'] === 'leaderboard_type') {
                $leaderboardType = $option['value'] ?? null;
                break;
            }
        }

        if (! $leaderboardType) {
            return $this->errorResponse('Leaderboard type is required.');
        }

        // Validate leaderboard type
        $validTypes = array_map(fn($type) => $type->value, LeaderboardType::cases());
        if (! in_array($leaderboardType, $validTypes, true)) {
            return $this->errorResponse('Invalid leaderboard type. Valid types: ' . implode(', ', $validTypes));
        }

        // Find the clan linked to this guild
        $clan = Clan::findByDiscordGuildId($guildId);

        if (! $clan) {
            return $this->errorResponse('This Discord server is not linked to any clan.');
        }

        // Calculate this week's date range
        $now = Carbon::now();
        $start = $now->copy()->subDays(7)->startOfDay();
        $end = $now->copy()->endOfDay();

        // Get leaderboard data
        $leaderboard = $this->leaderboardService->getLeaderboard($clan, $leaderboardType, $start, $end);

        if ($leaderboard->isEmpty()) {
            $typeLabel = ucfirst(str_replace('_', ' ', $leaderboardType));
            return $this->successResponse("No leaderboard data available for {$typeLabel} this week.");
        }

        // Format leaderboard type for display
        $typeLabel = ucfirst(str_replace('_', ' ', $leaderboardType));

        // Build embed fields (Discord allows max 25 fields, we'll show top 10)
        $fields = [];
        $topEntries = $leaderboard->take(10);

        foreach ($topEntries as $entry) {
            $user = $entry->user;
            $userName = $user ? ($user->name ?? $user->steam_persona_name ?? 'Unknown') : 'Unknown';
            $value = number_format((float) $entry->value, 2);

            // Add trophy emotes for top 3 positions
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

        // Format date range
        $dateRange = $start->format('M j') . ' - ' . $end->format('M j, Y');

        return [
            'type' => 4, // CHANNEL_MESSAGE_WITH_SOURCE
            'data' => [
                'embeds' => [
                    [
                        'title' => "Weekly {$typeLabel} Leaderboard",
                        'description' => "Period: {$dateRange}",
                        'fields' => $fields,
                        'footer' => [
                            'text' => $clan->name,
                        ],
                        'color' => 0x5865F2, // Discord blurple color
                    ],
                ],
                'flags' => 64, // EPHEMERAL
            ],
        ];
    }

    /**
     * Handle /match-report command
     *
     * @param  array  $payload
     * @return array
     */
    private function handleMatchReportCommand(array $payload): array
    {
        $options = $payload['data']['options'] ?? [];
        $matchId = null;

        foreach ($options as $option) {
            if ($option['name'] === 'id') {
                $matchId = $option['value'] ?? null;
                break;
            }
        }

        if (! $matchId) {
            return $this->errorResponse('Match ID is required.');
        }

        $match = \App\Models\GameMatch::with(['playerMatchEvents.player', 'players'])->find($matchId);

        if (! $match) {
            return $this->errorResponse("Match with ID {$matchId} not found.");
        }

        // Get all achievements for the match
        $achievements = \App\Models\Achievement::where('match_id', $matchId)
            ->with('player')
            ->get();

        // Format the match report embed
        $embed = $this->formatMatchReportEmbed($match, $achievements);

        return [
            'type' => 4, // CHANNEL_MESSAGE_WITH_SOURCE
            'data' => [
                'embeds' => [$embed],
                'flags' => 64, // EPHEMERAL
            ],
        ];
    }

    /**
     * Sanitize string to ensure valid UTF-8 encoding
     *
     * @param  string|null  $string
     * @return string
     */
    private function sanitizeUtf8(?string $string): string
    {
        if ($string === null) {
            return '';
        }

        // Convert to UTF-8 and remove invalid characters
        $string = mb_convert_encoding($string, 'UTF-8', 'UTF-8');

        // Remove any remaining invalid UTF-8 sequences
        $string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $string);

        // Use iconv to remove invalid characters if available
        if (function_exists('iconv')) {
            $string = @iconv('UTF-8', 'UTF-8//IGNORE', $string);
        }

        return $string ?: '';
    }

    /**
     * Format match report as Discord embed
     *
     * @param  \App\Models\GameMatch  $match
     * @param  \Illuminate\Database\Eloquent\Collection  $achievements
     * @param  \App\Models\Clan|null  $clan
     * @return array
     */
    private function formatMatchReportEmbed(\App\Models\GameMatch $match, $achievements, ?\App\Models\Clan $clan = null): array
    {
        $title = "Match Report #{$match->id}";
        $mapName = $this->sanitizeUtf8($match->map);
        $description = "**Map:** {$mapName}\n";
        $description .= "**Score:** {$match->winning_team_score} - {$match->losing_team_score}\n";
        if ($match->match_start_time) {
            $description .= "**Date:** {$match->match_start_time->format('M j, Y g:i A')}\n";
        }

        $fields = [];

        // Add achievements section if there are any
        if ($achievements->isNotEmpty()) {
            $achievementText = $this->formatAchievements($achievements, $clan);
            if ($achievementText) {
                // Add blank line before Achievements
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

        // Add scoreboard section
        // Add blank line before Scoreboard
        $fields[] = [
            'name' => '⠀', // Invisible character for spacing
            'value' => '⠀',
            'inline' => false,
        ];
        $scoreboardFields = $this->formatScoreboardFields($match, $clan);
        // Sanitize scoreboard field values
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

        // Build match report URL
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
     * @param  \App\Models\Clan|null  $clan
     * @return string
     */
    private function formatAchievements($achievements, ?\App\Models\Clan $clan = null): string
    {
        if ($clan) {
            // Filter achievements to only clan members
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
     *
     * @param  string  $string
     * @return int
     */
    private function getStringWidth(string $string): int
    {
        $width = 0;
        $length = mb_strlen($string, 'UTF-8');

        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($string, $i, 1, 'UTF-8');
            // Full-width characters (like Chinese, Japanese, Korean) count as 2
            // Regular characters count as 1
            $charWidth = mb_strwidth($char, 'UTF-8');
            $width += $charWidth;
        }

        return $width;
    }

    /**
     * Pad string to a specific visual width
     *
     * @param  string  $string
     * @param  int  $width
     * @param  string  $padString
     * @param  int  $padType
     * @return string
     */
    private function padToWidth(string $string, int $width, string $padString = ' ', int $padType = STR_PAD_RIGHT): string
    {
        $currentWidth = $this->getStringWidth($string);
        $paddingNeeded = $width - $currentWidth;

        if ($paddingNeeded <= 0) {
            return $string;
        }

        if ($padType === STR_PAD_RIGHT) {
            return $string . str_repeat($padString, $paddingNeeded);
        } elseif ($padType === STR_PAD_LEFT) {
            return str_repeat($padString, $paddingNeeded) . $string;
        } else {
            $left = (int) floor($paddingNeeded / 2);
            $right = $paddingNeeded - $left;
            return str_repeat($padString, $left) . $string . str_repeat($padString, $right);
        }
    }

    /**
     * Format scoreboard as Discord embed fields with teams side by side
     *
     * @param  \App\Models\GameMatch  $match
     * @param  \App\Models\Clan|null  $clan
     * @return array
     */
    private function formatScoreboardFields(\App\Models\GameMatch $match, ?\App\Models\Clan $clan = null): array
    {
        $playerMatchEvents = $match->playerMatchEvents;

        if ($clan) {
            // Filter to only clan members
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

        // Get team information for each player
        $teamA = [];
        $teamB = [];

        foreach ($playerMatchEvents as $event) {
            $player = $match->players->where('steam_id', $event->player_steam_id)->first();
            $teamValue = 'A';
            if ($player && $player->pivot) {
                $team = $player->pivot->team;
                // Handle both enum and string values
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

        // Sort both teams by kills descending
        usort($teamA, fn($a, $b) => $b['kills'] <=> $a['kills']);
        usort($teamB, fn($a, $b) => $b['kills'] <=> $a['kills']);

        // Helper function to format a team as a simple list
        $formatTeamList = function ($team, $teamName) {
            $lines = [];
            $lines[] = "**{$teamName}**";
            $lines[] = ''; // Blank line after header

            foreach ($team as $index => $player) {
                $playerName = $this->sanitizeUtf8($player['name']);
                $fkFormatted = "+{$player['first_kills']}/-{$player['first_deaths']}";

                $lines[] = $playerName;
                $lines[] = "K/D: {$player['kills']}/{$player['deaths']} | FK {$fkFormatted} | ADR: {$player['adr']}";

                // Add blank line between players (except after last one)
                if ($index < count($team) - 1) {
                    $lines[] = '';
                }
            }

            return implode("\n", $lines);
        };

        // Format both teams
        $teamAText = $formatTeamList($teamA, 'Team A');
        $teamBText = $formatTeamList($teamB, 'Team B');

        // Return as separate fields
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
     *
     * @param  int  $kills
     * @param  int  $deaths
     * @return float
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
     *
     * @param  \App\Models\GameMatch  $match
     * @param  \App\Models\Clan  $clan
     * @return void
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

        $botToken = config('services.discord.bot_token');

        if (! $botToken) {
            Log::warning('Discord bot token not configured');

            return;
        }

        // Ensure match relationships are loaded
        if (! $match->relationLoaded('playerMatchEvents')) {
            $match->load(['playerMatchEvents.player', 'players']);
        }

        // Get achievements for clan members in the match
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

        // Format the match report embed
        $embed = $this->formatMatchReportEmbed($match, $achievements, $clan);

        // Send to Discord channel
        $url = "https://discord.com/api/v10/channels/{$clan->discord_channel_id}/messages";

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bot {$botToken}",
                'Content-Type' => 'application/json',
            ])->post($url, [
                'embeds' => [$embed],
            ]);

            if ($response->successful()) {
                Log::info('Match report sent to Discord', [
                    'match_id' => $match->id,
                    'clan_id' => $clan->id,
                    'channel_id' => $clan->discord_channel_id,
                ]);
            } else {
                Log::error('Failed to send match report to Discord', [
                    'match_id' => $match->id,
                    'clan_id' => $clan->id,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Exception while sending match report to Discord', [
                'match_id' => $match->id,
                'clan_id' => $clan->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create error response for Discord interaction
     *
     * @param  string  $message
     * @return array
     */
    private function errorResponse(string $message): array
    {
        return [
            'type' => 4, // CHANNEL_MESSAGE_WITH_SOURCE
            'data' => [
                'content' => '❌ ' . $message,
                'flags' => 64, // EPHEMERAL
            ],
        ];
    }
}
