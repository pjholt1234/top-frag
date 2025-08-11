<?php

namespace App\Services;

use App\Enums\MatchType;
use App\Models\DemoProcessingJob;
use Illuminate\Support\Facades\Log;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\MatchPlayer;
use App\Enums\Team;
use App\Enums\MatchEventType;
use App\Models\GunfightEvent;
use App\Models\DamageEvent;
use App\Models\GrenadeEvent;

class DemoParserService
{
    public function updateProcessingJob(string $jobId, array $data, bool $isCompleted = false): void
    {
        $job = DemoProcessingJob::where('uuid', $jobId)->first();

        if (!$job) {
            Log::warning("Demo processing job not found for match event creation", ['job_id' => $jobId]);
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

        $match = $job->match;

        if (!$match) {
            Log::warning("Match not found for job", ['job_id' => $jobId]);
            return;
        }

        $match->update([
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

    public function createMatchEvent(string $jobId, array $eventData, string $eventName): void
    {
        $job = DemoProcessingJob::where('uuid', $jobId)->first();

        if (!$job) {
            return;
        }

        $match = $job->match;

        if (!$match) {
            Log::error("Match not found for job", ['job_id' => $jobId]);
            return;
        }

        match ($eventName) {
            MatchEventType::DAMAGE->value => $this->createDamageEvent($match, $eventData),
            MatchEventType::GUNFIGHT->value => $this->createGunfightEvent($match, $eventData),
            MatchEventType::GRENADE->value => $this->createGrenadeEvent($match, $eventData),
            default => Log::error("Invalid event name", ['job_id' => $jobId, 'event_name' => $eventName]),
        };
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
        foreach ($damageEvents as $damageEvent) {
            DamageEvent::create([
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
            ]);
        }
    }

    private function createGunfightEvent(GameMatch $match, array $gunFightEvents): void
    {
        foreach ($gunFightEvents as $gunFightEvent) {
            GunfightEvent::create([
                'match_id' => $match->id,
                'round_number' => $gunFightEvent['round_number'],
                'round_time' => $gunFightEvent['round_time'],
                'tick_timestamp' => $gunFightEvent['tick_timestamp'],
                'player_1_steam_id' => $gunFightEvent['player_1_steam_id'],
                'player_2_steam_id' => $gunFightEvent['player_2_steam_id'],
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
            ]);
        }
    }

    private function createGrenadeEvent(GameMatch $match, array $grenadeEvents): void
    {
        foreach ($grenadeEvents as $grenadeEvent) {
            GrenadeEvent::create([
                'match_id' => $match->id,
                'round_number' => $grenadeEvent['round_number'],
                'round_time' => $grenadeEvent['round_time'],
                'tick_timestamp' => $grenadeEvent['tick_timestamp'],
                'player_steam_id' => $grenadeEvent['player_steam_id'],
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
                'damage_dealt' => 0, //todo
                'flash_duration' => 0, //todo
                'affected_players', //todo
                'throw_type', // todo
                'effectiveness_rating', // todo
            ]);
        }
    }
}
