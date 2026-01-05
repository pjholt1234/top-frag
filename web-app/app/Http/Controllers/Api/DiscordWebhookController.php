<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Discord\DiscordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DiscordWebhookController extends Controller
{
    public function __construct(
        private readonly DiscordService $discordService
    ) {}

    /**
     * Handle Discord webhook/interaction requests
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::info('Discord webhook received', ['payload_keys' => array_keys($payload), 'type' => $payload['type'] ?? null]);

        // Handle PING first (Discord verification)
        if (isset($payload['type']) && $payload['type'] === 1) {
            return response()->json(['type' => 1]);
        }

        // Handle APPLICATION_COMMAND (slash commands)
        if (isset($payload['type']) && $payload['type'] === 2) {
            $response = $this->discordService->handleInteraction($payload);

            return response()->json($response);
        }

        // Handle MESSAGE_COMPONENT (select menus, buttons)
        if (isset($payload['type']) && $payload['type'] === 3) {
            $response = $this->discordService->handleInteraction($payload);

            return response()->json($response);
        }

        // Handle MODAL_SUBMIT (modal form submissions)
        if (isset($payload['type']) && $payload['type'] === 5) {
            $response = $this->discordService->handleInteraction($payload);

            return response()->json($response);
        }

        // Check if this is a bot installation event (legacy - for non-command interactions)
        // Discord sends guild_id in the interaction payload when bot is added to a server
        // The installer info is in member.user.id for guild interactions
        // Note: This is now mainly for backwards compatibility - slash commands handle setup
        // Only check this if it's not a known interaction type
        if (isset($payload['guild_id']) && isset($payload['member']['user']['id'])) {
            $guildId = $payload['guild_id'];
            $installerDiscordId = $payload['member']['user']['id'];
            $guildName = $payload['guild']['name'] ?? $payload['guild_id'];

            Log::info('Bot installation detected from interaction', [
                'guild_id' => $guildId,
                'installer_discord_id' => $installerDiscordId,
                'guild_name' => $guildName,
            ]);

            // Handle bot installation
            $response = $this->discordService->handleBotInstallation(
                $guildId,
                $guildName,
                $installerDiscordId
            );

            return response()->json($response);
        }

        // Handle other interaction types
        $response = $this->discordService->handleInteraction($payload);

        return response()->json($response);
    }
}
