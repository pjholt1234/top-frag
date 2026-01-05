<?php

namespace App\Services\Discord\Commands;

use App\Models\Clan;
use App\Services\Discord\DiscordService;

class MembersCommand implements CommandInterface
{
    public function __construct(
        private readonly DiscordService $discordService
    ) {}

    public function execute(array $payload): array
    {
        $guildId = $payload['guild_id'] ?? null;

        if (! $guildId) {
            return $this->discordService->errorResponse('This command can only be used in a Discord server.');
        }

        $clan = Clan::findByDiscordGuildId($guildId);

        if (! $clan) {
            return $this->discordService->errorResponse('This Discord server is not linked to any clan.');
        }

        $members = $clan->members()->with('user')->get();

        if ($members->isEmpty()) {
            return $this->discordService->successResponse('This clan has no members yet.');
        }

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
}
