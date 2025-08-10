<?php

namespace App\Services;

use App\Enums\MatchType;
use App\Models\DemoProcessingJob;
use App\Enums\ProcessingStatus;
use Illuminate\Support\Facades\Log;
use App\Models\GameMatch;
use Illuminate\Support\Str;
use App\Models\Player;
use App\Models\MatchPlayer;
use App\Enums\Team;

class DemoParserService
{
    public function updateProcessingJob(string $jobId, array $data, bool $isCompleted = false): void
    {
        $job = DemoProcessingJob::where('uuid', $jobId)->first();

        if (!$job) {
            return;
        }

        $job->update([
            'processing_status' => $data['status'],
            'progress_percentage' => $data['progress'] ?? 0,
            'completed_at' => $isCompleted ? now() : null,
            'current_step' => $data['current_step'] ?? ($isCompleted ? 'Completed' : null),
        ]);
    }

    public function createMatchWithPlayers(string $jobId, array $matchData, ?array $playersData = null): void
    {
        $job = DemoProcessingJob::where('uuid', $jobId)->first();

        if (!$job) {
            Log::warning("Demo processing job not found for match creation", ['job_id' => $jobId]);
            return;
        }

        $matchHash = $this->generateMatchHash($matchData, $playersData);

        $match = GameMatch::create([
            'match_hash' => $matchHash,
            'map' => $matchData['map'] ?? 'Unknown',
            'winning_team' => $matchData['winning_team'] ?? 'A',
            'winning_team_score' => $matchData['winning_team_score'] ?? 0,
            'losing_team_score' => $matchData['losing_team_score'] ?? 0,
            'match_type' => $this->mapMatchType($matchData['match_type'] ?? 'other'),
            'start_timestamp' => null, //todo: add this
            'end_timestamp' => null, //todo: add this
            'total_rounds' => $matchData['total_rounds'] ?? 0,
            'total_fight_events' => 0, // Will be updated when events are processed
            'total_grenade_events' => 0, // Will be updated when events are processed
            'playback_ticks' => $matchData['playback_ticks'] ?? 0,
        ]);


        $job->update(['match_id' => $match->id]);

        if ($playersData && is_array($playersData)) {
            foreach ($playersData as $playerData) {
                $this->createOrUpdatePlayer($match, $playerData);
            }
        }

        Log::info("Match created successfully", [
            'job_id' => $jobId,
            'match_id' => $match->id,
            'match_hash' => $matchHash,
            'players_count' => $playersData ? count($playersData) : 0
        ]);
    }

    private function createOrUpdatePlayer(GameMatch $match, array $playerData): void
    {
        $player = Player::firstOrCreate(
            ['steam_id' => $playerData['steam_id']],
            [
                'name' => $playerData['name'] ?? 'Unknown',
                'first_seen_at' => now(),
                'last_seen_at' => now(),
                'total_matches' => 1,
            ]
        );

        $player->update([
            'last_seen_at' => now(),
            'total_matches' => $player->total_matches + 1,
        ]);

        MatchPlayer::create([
            'match_id' => $match->id,
            'player_id' => $player->id,
            'team' => $this->mapTeam($playerData['team'] ?? 'A'),
        ]);
    }

    private function generateMatchHash(array $matchData, ?array $playersData = null): string
    {
        $hashData = [
            $matchData['map'] ?? 'Unknown',
            $matchData['winning_team_score'] ?? 0,
            $matchData['losing_team_score'] ?? 0,
            $matchData['match_type'] ?? 'other',
            $matchData['total_rounds'] ?? 0,
            $matchData['playback_ticks'] ?? 0,
        ];

        if ($playersData && is_array($playersData)) {
            usort($playersData, function ($a, $b) {
                return ($a['steam_id'] ?? '') <=> ($b['steam_id'] ?? '');
            });

            foreach ($playersData as $playerData) {
                $hashData[] = $playerData['steam_id'] ?? 'Unknown';
                $hashData[] = $playerData['team'] ?? 'A';
            }
        }

        return hash('sha256', implode('|', $hashData));
    }

    private function mapMatchType(string $type): string
    {
        return match (strtolower($type)) {
            'hltv' => MatchType::HLTV->value,
            'mm' => MatchType::MATCHMAKING->value,
            'faceit' => MatchType::FACEIT->value,
            'esportal' => MatchType::ESPORTAL->value,
            default => MatchType::OTHER->value,
        };
    }

    private function mapTeam(string $team): string
    {
        return match (strtoupper($team)) {
            'A' => Team::TEAM_A->value,
            'B' => Team::TEAM_B->value,
            default => Team::TEAM_A->value, // Default fallback
        };
    }

    private function validateAndGetStatus(?string $status): ?ProcessingStatus
    {
        if (!$status) {
            return null;
        }

        // Try to get the enum value from the API status
        $enumValue = ProcessingStatus::tryFrom($status);

        if (!$enumValue) {
            // Log unknown status for debugging
            Log::warning("Unknown processing status received from parser service", [
                'status' => $status,
                'known_statuses' => array_column(ProcessingStatus::cases(), 'value')
            ]);
        }

        return $enumValue;
    }

    public function getValidStatuses(): array
    {
        return array_column(ProcessingStatus::cases(), 'value');
    }

    public function isValidStatus(string $status): bool
    {
        return ProcessingStatus::tryFrom($status) !== null;
    }
}
