<?php

namespace App\Services;

use App\Enums\MatchEventType;
use App\Enums\MatchType;
use App\Enums\Team;
use App\Models\DamageEvent;
use App\Models\DemoProcessingJob;
use App\Models\GameMatch;
use App\Models\GrenadeEvent;
use App\Models\GunfightEvent;
use App\Models\MatchPlayer;
use App\Models\Player;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DemoParserService
{
    private array $jobCache = [];

    public function updateProcessingJob(string $jobId, array $data, bool $isCompleted = false): void
    {
        $job = $this->getJob($jobId);

        if (! $job) {
            Log::warning('Demo processing job not found for match event creation', ['job_id' => $jobId]);

            return;
        }

        $job->update([
            'processing_status' => $data['status'],
            'progress_percentage' => $isCompleted ? 100 : $data['progress'],
            'completed_at' => $isCompleted ? now() : null,
            'current_step' => $data['current_step'] ?? ($isCompleted ? 'Completed' : null),
        ]);

        // Clear cache after update to ensure fresh data
        $this->clearJobCache($jobId);
    }

    public function createMatchWithPlayers(string $jobId, array $matchData, ?array $playersData = null): void
    {
        $job = $this->getJob($jobId);

        if (! $job) {
            Log::warning('Demo processing job not found for match creation', ['job_id' => $jobId]);

            return;
        }

        $matchHash = $this->generateMatchHash($matchData, $playersData);

        $match = $job->match;

        if (! $match) {
            // Create a new match if it doesn't exist
            $match = GameMatch::create([
                'match_hash' => $matchHash,
                'map' => $matchData['map'] ?? 'Unknown',
                'winning_team' => $matchData['winning_team'] ?? 'A',
                'winning_team_score' => $matchData['winning_team_score'] ?? 0,
                'losing_team_score' => $matchData['losing_team_score'] ?? 0,
                'match_type' => $this->mapMatchType($matchData['match_type'] ?? 'other'),
                'start_timestamp' => null, // todo: add this
                'end_timestamp' => null, // todo: add this
                'total_rounds' => $matchData['total_rounds'] ?? 0,
                'total_fight_events' => 0, // Will be updated when events are processed
                'total_grenade_events' => 0, // Will be updated when events are processed
                'playback_ticks' => $matchData['playback_ticks'] ?? 0,
            ]);

            // Update the job to reference the new match
            $job->update(['match_id' => $match->id]);
        } else {
            // Update existing match
            $match->update([
                'match_hash' => $matchHash,
                'map' => $matchData['map'] ?? 'Unknown',
                'winning_team' => $matchData['winning_team'] ?? 'A',
                'winning_team_score' => $matchData['winning_team_score'] ?? 0,
                'losing_team_score' => $matchData['losing_team_score'] ?? 0,
                'match_type' => $this->mapMatchType($matchData['match_type'] ?? 'other'),
                'start_timestamp' => null, // todo: add this
                'end_timestamp' => null, // todo: add this
                'total_rounds' => $matchData['total_rounds'] ?? 0,
                'total_fight_events' => 0, // Will be updated when events are processed
                'total_grenade_events' => 0, // Will be updated when events are processed
                'playback_ticks' => $matchData['playback_ticks'] ?? 0,
            ]);
        }

        if ($playersData && is_array($playersData)) {
            foreach ($playersData as $playerData) {
                $this->createOrUpdatePlayer($match, $playerData);
            }
        }

        Log::info('Match created successfully', [
            'job_id' => $jobId,
            'match_id' => $match->id,
            'match_hash' => $matchHash,
            'players_count' => $playersData ? count($playersData) : 0,
        ]);
    }

    public function createMatchEvent(string $jobId, array $eventData, string $eventName): void
    {
        $job = $this->getJob($jobId);

        if (! $job) {
            return;
        }

        $match = $job->match;

        if (! $match) {
            Log::error('Match not found for job', ['job_id' => $jobId]);

            return;
        }

        // Use database transaction for better performance and data consistency
        DB::transaction(function () use ($match, $eventData, $eventName, $jobId) {
            match ($eventName) {
                MatchEventType::DAMAGE->value => $this->createDamageEvent($match, $eventData),
                MatchEventType::GUNFIGHT->value => $this->createGunfightEvent($match, $eventData),
                MatchEventType::GRENADE->value => $this->createGrenadeEvent($match, $eventData),
                default => Log::error('Invalid event name', ['job_id' => $jobId, 'event_name' => $eventName]),
            };
        });
    }

    /**
     * Get job with caching to avoid repeated database queries
     */
    private function getJob(string $jobId): ?DemoProcessingJob
    {
        if (! isset($this->jobCache[$jobId])) {
            $this->jobCache[$jobId] = DemoProcessingJob::with('match')->where('uuid', $jobId)->first();
        }

        return $this->jobCache[$jobId];
    }

    /**
     * Clear job cache when needed (e.g., after job completion)
     */
    public function clearJobCache(?string $jobId = null): void
    {
        if ($jobId) {
            unset($this->jobCache[$jobId]);
        } else {
            $this->jobCache = [];
        }
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

    private function generateMatchHash(array $matchData, ?array $playersData = null): ?string
    {
        if (app()->environment('local')) {
            return null;
        }

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
            default => Team::TEAM_A->value,
        };
    }

    private function createDamageEvent(GameMatch $match, array $damageEvents): void
    {
        if (empty($damageEvents)) {
            return;
        }

        // Process in chunks to avoid memory issues with large datasets
        $chunks = array_chunk($damageEvents, 1000);

        foreach ($chunks as $chunk) {
            $records = [];
            $now = now();

            foreach ($chunk as $damageEvent) {
                $records[] = [
                    'match_id' => $match->id,
                    'armor_damage' => $damageEvent['armor_damage'],
                    'attacker_steam_id' => $damageEvent['attacker_steam_id'],
                    'damage' => $damageEvent['damage'],
                    'headshot' => $damageEvent['headshot'],
                    'health_damage' => $damageEvent['health_damage'],
                    'round_number' => $damageEvent['round_number'],
                    'round_time' => $damageEvent['round_time'],
                    'tick_timestamp' => $damageEvent['tick_timestamp'],
                    'victim_steam_id' => $damageEvent['victim_steam_id'],
                    'weapon' => $damageEvent['weapon'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // Use bulk insert for better performance
            DamageEvent::insert($records);
        }
    }

    private function createGunfightEvent(GameMatch $match, array $gunFightEvents): void
    {
        if (empty($gunFightEvents)) {
            return;
        }

        // Process in chunks to avoid memory issues with large datasets
        $chunks = array_chunk($gunFightEvents, 1000);

        foreach ($chunks as $chunk) {
            $records = [];
            $now = now();

            foreach ($chunk as $gunFightEvent) {
                $records[] = [
                    'match_id' => $match->id,
                    'round_number' => $gunFightEvent['round_number'],
                    'round_time' => $gunFightEvent['round_time'],
                    'tick_timestamp' => $gunFightEvent['tick_timestamp'],
                    'player_1_steam_id' => $gunFightEvent['player_1_steam_id'],
                    'player_1_side' => $gunFightEvent['player_1_side'] ?? null,
                    'player_2_steam_id' => $gunFightEvent['player_2_steam_id'],
                    'player_2_side' => $gunFightEvent['player_2_side'] ?? null,
                    'player_1_hp_start' => $gunFightEvent['player_1_hp_start'],
                    'player_2_hp_start' => $gunFightEvent['player_2_hp_start'],
                    'player_1_armor' => $gunFightEvent['player_1_armor'],
                    'player_2_armor' => $gunFightEvent['player_2_armor'],
                    'player_1_equipment_value' => $gunFightEvent['player_1_equipment_value'],
                    'player_2_equipment_value' => $gunFightEvent['player_2_equipment_value'],
                    'player_1_flashed' => $gunFightEvent['player_1_flashed'],
                    'player_2_flashed' => $gunFightEvent['player_2_flashed'],
                    'player_1_weapon' => $gunFightEvent['player_1_weapon'],
                    'player_2_weapon' => $gunFightEvent['player_2_weapon'],
                    'player_1_x' => $gunFightEvent['player_1_x'],
                    'player_1_y' => $gunFightEvent['player_1_y'],
                    'player_1_z' => $gunFightEvent['player_1_z'],
                    'player_2_x' => $gunFightEvent['player_2_x'],
                    'player_2_y' => $gunFightEvent['player_2_y'],
                    'player_2_z' => $gunFightEvent['player_2_z'],
                    'distance' => $gunFightEvent['distance'],
                    'headshot' => $gunFightEvent['headshot'],
                    'wallbang' => $gunFightEvent['wallbang'],
                    'penetrated_objects' => $gunFightEvent['penetrated_objects'],
                    'victor_steam_id' => $gunFightEvent['victor_steam_id'],
                    'damage_dealt' => $gunFightEvent['damage_dealt'],
                    'is_first_kill' => $gunFightEvent['is_first_kill'] ?? false,
                    'flash_assister_steam_id' => $gunFightEvent['flash_assister_steam_id'] ?? null,
                    'damage_assist_steam_id' => $gunFightEvent['damage_assist_steam_id'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // Use bulk insert for better performance
            GunfightEvent::insert($records);
        }
    }

    private function createGrenadeEvent(GameMatch $match, array $grenadeEvents): void
    {
        if (empty($grenadeEvents)) {
            return;
        }

        // Process in chunks to avoid memory issues with large datasets
        $chunks = array_chunk($grenadeEvents, 1000);

        foreach ($chunks as $chunk) {
            $records = [];
            $now = now();

            foreach ($chunk as $grenadeEvent) {
                $records[] = [
                    'match_id' => $match->id,
                    'round_number' => $grenadeEvent['round_number'],
                    'round_time' => $grenadeEvent['round_time'],
                    'tick_timestamp' => $grenadeEvent['tick_timestamp'],
                    'player_steam_id' => $grenadeEvent['player_steam_id'],
                    'player_side' => $grenadeEvent['player_side'] ?? null,
                    'grenade_type' => $grenadeEvent['grenade_type'],
                    'player_x' => $grenadeEvent['player_x'],
                    'player_y' => $grenadeEvent['player_y'],
                    'player_z' => $grenadeEvent['player_z'],
                    'player_aim_x' => $grenadeEvent['player_aim_x'],
                    'player_aim_y' => $grenadeEvent['player_aim_y'],
                    'player_aim_z' => $grenadeEvent['player_aim_z'],
                    'grenade_final_x' => $grenadeEvent['grenade_final_x'],
                    'grenade_final_y' => $grenadeEvent['grenade_final_y'],
                    'grenade_final_z' => $grenadeEvent['grenade_final_z'],
                    'damage_dealt' => $grenadeEvent['damage_dealt'] ?? 0,
                    'flash_duration' => $grenadeEvent['flash_duration'] ?? null,
                    'friendly_flash_duration' => $grenadeEvent['friendly_flash_duration'] ?? null,
                    'enemy_flash_duration' => $grenadeEvent['enemy_flash_duration'] ?? null,
                    'friendly_players_affected' => $grenadeEvent['friendly_players_affected'] ?? 0,
                    'enemy_players_affected' => $grenadeEvent['enemy_players_affected'] ?? 0,
                    'throw_type' => $grenadeEvent['throw_type'] ?? 'utility',
                    'effectiveness_rating' => $grenadeEvent['effectiveness_rating'] ?? null,
                    'flash_leads_to_kill' => $grenadeEvent['flash_leads_to_kill'] ?? false,
                    'flash_leads_to_death' => $grenadeEvent['flash_leads_to_death'] ?? false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // Use bulk insert for better performance
            GrenadeEvent::insert($records);
        }
    }
}
