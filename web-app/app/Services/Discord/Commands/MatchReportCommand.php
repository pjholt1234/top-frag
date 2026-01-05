<?php

namespace App\Services\Discord\Commands;

use App\Services\Discord\DiscordService;

class MatchReportCommand implements CommandInterface
{
    public function __construct(
        private readonly DiscordService $discordService
    ) {}

    public function execute(array $payload): array
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
            return $this->discordService->errorResponse('Match ID is required.');
        }

        $match = \App\Models\GameMatch::with(['playerMatchEvents.player', 'players'])->find($matchId);

        if (! $match) {
            return $this->discordService->errorResponse("Match with ID {$matchId} not found.");
        }

        $achievements = \App\Models\Achievement::where('match_id', $matchId)
            ->with('player')
            ->get();

        $embed = $this->discordService->formatMatchReportEmbed($match, $achievements);

        return [
            'type' => 4, // CHANNEL_MESSAGE_WITH_SOURCE
            'data' => [
                'embeds' => [$embed],
                'flags' => 64, // EPHEMERAL
            ],
        ];
    }
}
