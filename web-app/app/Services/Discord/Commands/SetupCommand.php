<?php

namespace App\Services\Discord\Commands;

use App\Models\Clan;
use App\Models\User;
use App\Services\Clans\ClanService;
use App\Services\Discord\DiscordService;
use App\Services\Integrations\Discord\DiscordRepository;
use Illuminate\Support\Facades\Log;

class SetupCommand implements CommandInterface
{
    public function __construct(
        private readonly ClanService $clanService,
        private readonly DiscordRepository $discordRepository,
        private readonly DiscordService $discordService
    ) {}

    public function execute(array $payload): array
    {
        $guildId = $payload['guild_id'] ?? null;
        $guildName = $payload['guild']['name'] ?? 'Unknown Guild';

        $installerDiscordId = $payload['member']['user']['id'] ?? $payload['user']['id'] ?? null;

        if (! $guildId || ! $installerDiscordId) {
            Log::warning('Setup command missing required data', [
                'has_guild_id' => ! empty($guildId),
                'has_installer_id' => ! empty($installerDiscordId),
            ]);

            return $this->discordService->errorResponse('This command can only be used in a Discord server.');
        }

        $user = User::where('discord_id', $installerDiscordId)
            ->whereNotNull('discord_id')
            ->first();

        if (! $user) {
            return $this->discordService->errorResponse('You must be a Top Frag member with a linked Discord account to use this command.');
        }

        $existingClan = Clan::findByDiscordGuildId($guildId);
        if ($existingClan) {
            return $this->discordService->errorResponse('This Discord server is already linked to clan "'.$existingClan->name.'". Use `/unlink-clan` to unlink it first.');
        }

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
     * Handle setup option selection (create new or link existing)
     */
    public function handleSetupOption(array $payload, string $option): array
    {
        $guildId = $payload['guild_id'] ?? null;
        $installerDiscordId = $payload['member']['user']['id'] ?? $payload['user']['id'] ?? null;

        if (! $guildId || ! $installerDiscordId) {
            return $this->discordService->errorResponse('Missing required data.');
        }

        $user = User::where('discord_id', $installerDiscordId)
            ->whereNotNull('discord_id')
            ->first();

        if (! $user) {
            return $this->discordService->errorResponse('User not found.');
        }

        if ($option === 'create_new') {
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
            $unlinkedClans = Clan::where('owned_by', $user->id)
                ->whereNull('discord_guild_id')
                ->get();

            if ($unlinkedClans->isEmpty()) {
                return $this->discordService->errorResponse('You don\'t have any unlinked clans. Use "Create new clan" instead.');
            }

            $options = [];
            foreach ($unlinkedClans as $clan) {
                $options[] = [
                    'label' => $clan->name.' - '.$clan->tag,
                    'value' => $clan->tag, // Use tag as identifier since it's unique
                    'description' => 'Tag: '.$clan->tag,
                ];
            }

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

        return $this->discordService->errorResponse('Unknown option.');
    }

    /**
     * Handle clan selection for linking
     */
    public function handleLinkClanSelection(array $payload, string $clanTag): array
    {
        $guildId = $payload['guild_id'] ?? null;
        $guildName = $payload['guild']['name'] ?? 'Unknown Guild';
        $installerDiscordId = $payload['member']['user']['id'] ?? $payload['user']['id'] ?? null;

        if (! $guildId || ! $installerDiscordId) {
            return $this->discordService->errorResponse('Missing required data.');
        }

        $user = User::where('discord_id', $installerDiscordId)
            ->whereNotNull('discord_id')
            ->first();

        if (! $user) {
            return $this->discordService->errorResponse('User not found.');
        }

        $clan = Clan::where('tag', $clanTag)
            ->where('owned_by', $user->id)
            ->whereNull('discord_guild_id')
            ->first();

        if (! $clan) {
            return $this->discordService->errorResponse('Clan not found or already linked.');
        }

        try {
            $this->clanService->linkToDiscordGuild($clan, $guildId, $user);

            $this->discordService->addDiscordGuildMembersToClan($clan, $guildId);

            return $this->discordService->showChannelSelectionMenu($guildId, $clan, true);
        } catch (\Exception $e) {
            Log::error('Failed to link clan', [
                'clan_tag' => $clanTag,
                'guild_id' => $guildId,
                'error' => $e->getMessage(),
            ]);

            return $this->discordService->errorResponse('Failed to link clan: '.$e->getMessage());
        }
    }

    /**
     * Handle create clan modal submission
     */
    public function handleCreateClanModal(array $payload, array $components): array
    {
        $guildId = $payload['guild_id'] ?? null;
        $guildName = $payload['guild']['name'] ?? 'Unknown Guild';
        $installerDiscordId = $payload['member']['user']['id'] ?? $payload['user']['id'] ?? null;

        if (! $guildId || ! $installerDiscordId) {
            return $this->discordService->errorResponse('Missing required data.');
        }

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

        if (empty($clanName) || empty($clanTag)) {
            return $this->discordService->errorResponse('Clan name and tag are required.');
        }

        $nameValidation = $this->discordService->validateClanName($clanName);
        if ($nameValidation !== null) {
            return $this->discordService->errorResponse($nameValidation);
        }

        $tagValidation = $this->discordService->validateClanTag($clanTag);
        if ($tagValidation !== null) {
            return $this->discordService->errorResponse($tagValidation);
        }

        Log::info('Create clan modal submitted', [
            'guild_id' => $guildId,
            'guild_name' => $guildName,
            'installer_discord_id' => $installerDiscordId,
            'clan_name' => $clanName,
            'clan_tag' => $clanTag,
        ]);

        $response = $this->discordService->handleBotInstallation($guildId, $guildName, $installerDiscordId, $clanName, $clanTag);

        if (isset($response['type']) && $response['type'] === 4) {
            return $response;
        }

        return $this->discordService->successResponse('Clan created successfully!');
    }
}
