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
            'current_step' => $isCompleted ? 'Completed' : $data['current_step'],
        ]);
    }

    public function createMatchWithPlayers(string $jobId, array $matchData, ?array $playersData = null): void
    {
        $job = DemoProcessingJob::where('uuid', $jobId)->first();

        if (!$job) {
            Log::warning("Demo processing job not found for match creation", ['job_id' => $jobId]);
            return;
        }

        // Create the match
        $match = GameMatch::create([
            'match_hash' => Str::uuid(),
            'map' => $matchData['map'] ?? 'Unknown',
            'winning_team_score' => $matchData['winning_team_score'] ?? 0,
            'losing_team_score' => $matchData['losing_team_score'] ?? 0,
            'match_type' => $this->mapMatchType($matchData['match_type'] ?? 'other'),
            'start_timestamp' => isset($matchData['start_timestamp']) ? \Carbon\Carbon::parse($matchData['start_timestamp']) : null,
            'end_timestamp' => isset($matchData['end_timestamp']) ? \Carbon\Carbon::parse($matchData['end_timestamp']) : null,
            'total_rounds' => $matchData['total_rounds'] ?? 0,
            'total_fight_events' => 0, // Will be updated when events are processed
            'total_grenade_events' => 0, // Will be updated when events are processed
        ]);

        // Update the job with the match ID
        $job->update(['match_id' => $match->id]);

        // Process players if provided
        if ($playersData && is_array($playersData)) {
            foreach ($playersData as $playerData) {
                $this->createOrUpdatePlayer($match, $playerData);
            }
        }

        Log::info("Match created successfully", [
            'job_id' => $jobId,
            'match_id' => $match->id,
            'players_count' => $playersData ? count($playersData) : 0
        ]);
    }

    private function createOrUpdatePlayer(GameMatch $match, array $playerData): void
    {
        // Find or create the player
        $player = Player::firstOrCreate(
            ['steam_id' => $playerData['steam_id']],
            [
                'name' => $playerData['name'] ?? 'Unknown',
                'first_seen_at' => now(),
                'last_seen_at' => now(),
                'total_matches' => 1,
            ]
        );

        // Update player's last seen and total matches
        $player->update([
            'last_seen_at' => now(),
            'total_matches' => $player->total_matches + 1,
        ]);

        // Create the match player relationship
        MatchPlayer::create([
            'match_id' => $match->id,
            'player_id' => $player->id,
            'team' => $this->mapTeam($playerData['team'] ?? 'unknown'),
            'side_start' => $this->mapTeam($playerData['team'] ?? 'unknown'), // Assuming same as team for now
        ]);
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
        return match (strtolower($team)) {
            't', 'terrorist', 'terrorists' => Team::TERRORIST->value,
            'ct', 'counter-terrorist', 'counter-terrorists' => Team::COUNTER_TERRORIST->value,
            default => Team::TERRORIST->value, // Default to T instead of 'Unknown'
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
