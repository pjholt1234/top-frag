<?php

namespace App\Services\Discord\Commands;

use App\Enums\LeaderboardType;
use App\Models\Clan;
use App\Services\Clans\ClanLeaderboardService;
use App\Services\Discord\DiscordService;
use Carbon\Carbon;

class LeaderboardCommand implements CommandInterface
{
    public function __construct(
        private readonly ClanLeaderboardService $leaderboardService,
        private readonly DiscordService $discordService
    ) {}

    public function execute(array $payload): array
    {
        $guildId = $payload['guild_id'] ?? null;

        if (! $guildId) {
            return $this->discordService->errorResponse('This command can only be used in a Discord server.');
        }

        $options = $payload['data']['options'] ?? [];
        $leaderboardType = null;

        foreach ($options as $option) {
            if ($option['name'] === 'leaderboard_type') {
                $leaderboardType = $option['value'] ?? null;
                break;
            }
        }

        if (! $leaderboardType) {
            return $this->discordService->errorResponse('Leaderboard type is required.');
        }

        $validTypes = array_map(fn ($type) => $type->value, LeaderboardType::cases());
        if (! in_array($leaderboardType, $validTypes, true)) {
            return $this->discordService->errorResponse('Invalid leaderboard type. Valid types: '.implode(', ', $validTypes));
        }

        $clan = Clan::findByDiscordGuildId($guildId);

        if (! $clan) {
            return $this->discordService->errorResponse('This Discord server is not linked to any clan.');
        }

        $now = Carbon::now();
        $start = $now->copy()->subDays(7)->startOfDay();
        $end = $now->copy()->endOfDay();

        $leaderboard = $this->leaderboardService->getLeaderboard($clan, $leaderboardType, $start, $end);

        if ($leaderboard->isEmpty()) {
            $typeLabel = ucfirst(str_replace('_', ' ', $leaderboardType));

            return $this->discordService->successResponse("No leaderboard data available for {$typeLabel} this week.");
        }

        $typeLabel = ucfirst(str_replace('_', ' ', $leaderboardType));
        $typeEmoji = $this->discordService->getLeaderboardTypeEmoji($leaderboardType);
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

        $dateRange = $start->format('M j').' - '.$end->format('M j, Y');

        return [
            'type' => 4, // CHANNEL_MESSAGE_WITH_SOURCE
            'data' => [
                'embeds' => [
                    [
                        'title' => "{$titlePrefix}Weekly {$typeLabel} Leaderboard",
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
}
