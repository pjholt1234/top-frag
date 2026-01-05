<?php

namespace App\Services\Integrations\Discord;

use App\Exceptions\DiscordAPIConnectorException;

class DiscordRepository
{
    public function __construct(
        private readonly DiscordAPIConnector $connector
    ) {}

    /**
     * Get all channels for a guild.
     *
     * @param  string  $guildId  The Discord guild ID
     * @return array<string, mixed> Array of channel data
     *
     * @throws DiscordAPIConnectorException
     */
    public function getGuildChannels(string $guildId): array
    {
        $channels = $this->connector->get("guilds/{$guildId}/channels", []);

        // Sort by position
        usort($channels, fn ($a, $b) => ($a['position'] ?? 999) <=> ($b['position'] ?? 999));

        return $channels;
    }

    /**
     * Get all members for a guild (paginated).
     *
     * @param  string  $guildId  The Discord guild ID
     * @param  int  $limit  Maximum number of members per request (max 1000)
     * @return array<string> Array of Discord user IDs
     *
     * @throws DiscordAPIConnectorException
     */
    public function getGuildMembers(string $guildId, int $limit = 1000): array
    {
        $discordUserIds = [];
        $after = null;
        $maxMembers = 10000; // Safety limit

        do {
            $queryParams = ['limit' => min($limit, 1000)];
            if ($after) {
                $queryParams['after'] = $after;
            }

            $members = $this->connector->get("guilds/{$guildId}/members", $queryParams);

            if (empty($members)) {
                break;
            }

            foreach ($members as $member) {
                if (isset($member['user']['id'])) {
                    $discordUserIds[] = $member['user']['id'];
                }
            }

            if (count($members) < $limit) {
                break;
            }

            $lastMember = end($members);
            $after = $lastMember['user']['id'] ?? null;
        } while ($after !== null && count($discordUserIds) < $maxMembers);

        return array_unique($discordUserIds);
    }

    /**
     * Get channel details by ID.
     *
     * @param  string  $channelId  The Discord channel ID
     * @return array<string, mixed> Channel data
     *
     * @throws DiscordAPIConnectorException
     */
    public function getChannel(string $channelId): array
    {
        return $this->connector->get("channels/{$channelId}", []);
    }

    /**
     * Send a message to a Discord channel.
     *
     * @param  string  $channelId  The Discord channel ID
     * @param  array<string, mixed>  $data  Message data (content, embeds, etc.)
     * @return array<string, mixed> Response data
     *
     * @throws DiscordAPIConnectorException
     */
    public function sendMessage(string $channelId, array $data): array
    {
        return $this->connector->post("channels/{$channelId}/messages", $data);
    }

    /**
     * Get guild information.
     *
     * @param  string  $guildId  The Discord guild ID
     * @return array<string, mixed> Guild data
     *
     * @throws DiscordAPIConnectorException
     */
    public function getGuild(string $guildId): array
    {
        return $this->connector->get("guilds/{$guildId}", []);
    }
}
