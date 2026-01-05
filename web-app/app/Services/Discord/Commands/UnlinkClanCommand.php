<?php

namespace App\Services\Discord\Commands;

use App\Models\Clan;
use App\Models\User;
use App\Services\Discord\DiscordService;
use Illuminate\Support\Facades\Log;

class UnlinkClanCommand implements CommandInterface
{
    public function __construct(
        private readonly DiscordService $discordService
    ) {}

    public function execute(array $payload): array
    {
        $guildId = $payload['guild_id'] ?? null;
        $installerDiscordId = $payload['member']['user']['id'] ?? $payload['user']['id'] ?? null;

        if (! $guildId || ! $installerDiscordId) {
            return $this->discordService->errorResponse('This command can only be used in a Discord server.');
        }

        $user = User::where('discord_id', $installerDiscordId)
            ->whereNotNull('discord_id')
            ->first();

        if (! $user) {
            return $this->discordService->errorResponse('You must be a Top Frag member with a linked Discord account to use this command.');
        }

        $clan = Clan::findByDiscordGuildId($guildId);

        if (! $clan) {
            return $this->discordService->errorResponse('This Discord server is not linked to any clan.');
        }

        if (! $clan->isOwner($user)) {
            return $this->discordService->errorResponse('Only the clan owner can unlink the clan from Discord.');
        }

        try {
            $clan->discord_guild_id = null;
            $clan->save();

            Log::info('Clan unlinked from Discord server', [
                'clan_id' => $clan->id,
                'guild_id' => $guildId,
                'user_id' => $user->id,
            ]);

            return $this->discordService->successResponse('Clan "'.$clan->name.'" has been unlinked from this Discord server.');
        } catch (\Exception $e) {
            Log::error('Failed to unlink clan', [
                'clan_id' => $clan->id,
                'guild_id' => $guildId,
                'error' => $e->getMessage(),
            ]);

            return $this->discordService->errorResponse('Failed to unlink clan: '.$e->getMessage());
        }
    }
}
