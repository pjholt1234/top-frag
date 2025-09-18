<?php

namespace App\Services;

use App\Enums\MatchEventType;
use App\Enums\MatchType;
use App\Enums\ProcessingStatus;
use App\Enums\Team;
use App\Exceptions\DemoParserJobNotFoundException;
use App\Exceptions\DemoParserMatchNotFoundException;
use App\Models\DamageEvent;
use App\Models\DemoProcessingJob;
use App\Models\GameMatch;
use App\Models\GrenadeEvent;
use App\Models\GunfightEvent;
use App\Models\MatchPlayer;
use App\Models\Player;
use App\Models\PlayerMatchEvent;
use App\Models\PlayerRank;
use App\Models\PlayerRoundEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DemoParserService
{
    private array $jobCache = [];

    /**
     * @throws DemoParserJobNotFoundException
     */
    public function updateProcessingJob(string $jobId, array $data, bool $isCompleted = false): void
    {
        $job = $this->getJob($jobId);

        $updateData = [
            'processing_status' => $isCompleted ? ProcessingStatus::COMPLETED->value : $data['status'],
            'progress_percentage' => $isCompleted ? 100 : $data['progress'],
            'completed_at' => $isCompleted ? now() : null,
            'current_step' => $data['current_step'] ?? ($isCompleted ? 'Completed' : null),
        ];

        // Add error_message if it exists
        if (isset($data['error_message'])) {
            $updateData['error_message'] = $data['error_message'];
        }

        // Add new progress tracking fields if they exist
        if (isset($data['step_progress'])) {
            $updateData['step_progress'] = $data['step_progress'];
        }
        if (isset($data['total_steps'])) {
            $updateData['total_steps'] = $data['total_steps'];
        }
        if (isset($data['current_step_num'])) {
            $updateData['current_step_num'] = $data['current_step_num'];
        }
        if (isset($data['start_time'])) {
            $updateData['start_time'] = $data['start_time'];
        }
        if (isset($data['last_update_time'])) {
            $updateData['last_update_time'] = $data['last_update_time'];
        }
        if (isset($data['error_code'])) {
            $updateData['error_code'] = $data['error_code'];
        }
        if (isset($data['context'])) {
            $updateData['context'] = $data['context'];
        }
        if (isset($data['is_final'])) {
            $updateData['is_final'] = $data['is_final'];
        }

        $job->update($updateData);

        $this->clearJobCache($jobId);
    }

    /**
     * @throws DemoParserJobNotFoundException
     */
    public function createMatchWithPlayers(string $jobId, array $matchData, ?array $playersData = null): void
    {
        $job = $this->getJob($jobId);

        $matchHash = $this->generateMatchHash($matchData, $playersData);

        $match = $job->match;

        if (! $match) {
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
                'playback_ticks' => $matchData['playback_ticks'] ?? 0,
            ]);

            $job->update(['match_id' => $match->id]);
        } else {
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
                'playback_ticks' => $matchData['playback_ticks'] ?? 0,
            ]);
        }

        if (empty($playersData)) {
            return;
        }

        foreach ($playersData as $playerData) {
            $this->createOrUpdatePlayer($match, $playerData);
        }
    }

    /**
     * @throws DemoParserMatchNotFoundException|DemoParserJobNotFoundException
     */
    public function createMatchEvent(string $jobId, array $eventData, string $eventName): void
    {
        $job = $this->getJob($jobId);

        if (! $job) {
            return;
        }

        if (! $job->match) {
            throw new DemoParserMatchNotFoundException('Match not found for match creation');
        }

        $match = $job->match;

        DB::transaction(function () use ($match, $eventData, $eventName) {
            match ($eventName) {
                MatchEventType::DAMAGE->value => $this->createDamageEvent($match, $eventData),
                MatchEventType::GUNFIGHT->value => $this->createGunfightEvent($match, $eventData),
                MatchEventType::GRENADE->value => $this->createGrenadeEvent($match, $eventData),
                MatchEventType::PLAYER_ROUND->value => $this->createPlayerRoundEvent($match, $eventData),
                MatchEventType::PLAYER_MATCH->value => $this->createPlayerMatchEvent($match, $eventData),
                MatchEventType::MATCH->value => $this->updateMatchData($match, $eventData),
                default => Log::warning('Match event not found', [
                    'event_name' => $eventName,
                ]),
            };
        });
    }

    /**
     * Get job with caching to avoid repeated database queries
     *
     * @throws DemoParserJobNotFoundException
     */
    private function getJob(string $jobId): ?DemoProcessingJob
    {
        if (! isset($this->jobCache[$jobId])) {
            $this->jobCache[$jobId] = DemoProcessingJob::with('match')->where('uuid', $jobId)->first();
        }

        if (empty($this->jobCache[$jobId])) {
            throw new DemoParserJobNotFoundException('Job not found for match creation');
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
                'total_matches' => 0,
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

        if ($playersData) {
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
                    'round_scenario' => $gunFightEvent['round_scenario'] ?? null,
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
                    'team_damage_dealt' => $grenadeEvent['team_damage_dealt'] ?? 0,
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

    private function createPlayerRoundEvent(GameMatch $match, array $playerRoundEvents): void
    {
        if (empty($playerRoundEvents)) {
            return;
        }

        // Process in chunks to avoid memory issues with large datasets
        $chunks = array_chunk($playerRoundEvents, 1000);

        foreach ($chunks as $chunk) {
            $records = [];
            $now = now();

            foreach ($chunk as $playerRoundEvent) {
                $records[] = [
                    'match_id' => $match->id,
                    'player_steam_id' => $playerRoundEvent['player_steam_id'],
                    'round_number' => $playerRoundEvent['round_number'],

                    // Gun Fight fields
                    'kills' => $playerRoundEvent['kills'] ?? 0,
                    'assists' => $playerRoundEvent['assists'] ?? 0,
                    'died' => $playerRoundEvent['died'] ?? false,
                    'damage' => $playerRoundEvent['damage'] ?? 0,
                    'headshots' => $playerRoundEvent['headshots'] ?? 0,
                    'first_kill' => $playerRoundEvent['first_kill'] ?? false,
                    'first_death' => $playerRoundEvent['first_death'] ?? false,
                    'round_time_of_death' => $playerRoundEvent['round_time_of_death'] ?? null,
                    'kills_with_awp' => $playerRoundEvent['kills_with_awp'] ?? 0,

                    // Grenade fields
                    'damage_dealt' => $playerRoundEvent['damage_dealt'] ?? 0,
                    'flashes_thrown' => $playerRoundEvent['flashes_thrown'] ?? 0,
                    'fire_grenades_thrown' => $playerRoundEvent['fire_grenades_thrown'] ?? 0,
                    'smokes_thrown' => $playerRoundEvent['smokes_thrown'] ?? 0,
                    'hes_thrown' => $playerRoundEvent['hes_thrown'] ?? 0,
                    'decoys_thrown' => $playerRoundEvent['decoys_thrown'] ?? 0,
                    'friendly_flash_duration' => $playerRoundEvent['friendly_flash_duration'] ?? 0,
                    'enemy_flash_duration' => $playerRoundEvent['enemy_flash_duration'] ?? 0,
                    'friendly_players_affected' => $playerRoundEvent['friendly_players_affected'] ?? 0,
                    'enemy_players_affected' => $playerRoundEvent['enemy_players_affected'] ?? 0,
                    'flashes_leading_to_kill' => $playerRoundEvent['flashes_leading_to_kill'] ?? 0,
                    'flashes_leading_to_death' => $playerRoundEvent['flashes_leading_to_death'] ?? 0,
                    'grenade_effectiveness' => $playerRoundEvent['grenade_effectiveness'] ?? 0,

                    // Trade fields
                    'successful_trades' => $playerRoundEvent['successful_trades'] ?? 0,
                    'total_possible_trades' => $playerRoundEvent['total_possible_trades'] ?? 0,
                    'successful_traded_deaths' => $playerRoundEvent['successful_traded_deaths'] ?? 0,
                    'total_possible_traded_deaths' => $playerRoundEvent['total_possible_traded_deaths'] ?? 0,

                    // Clutch fields
                    'clutch_attempts_1v1' => $playerRoundEvent['clutch_attempts_1v1'] ?? 0,
                    'clutch_attempts_1v2' => $playerRoundEvent['clutch_attempts_1v2'] ?? 0,
                    'clutch_attempts_1v3' => $playerRoundEvent['clutch_attempts_1v3'] ?? 0,
                    'clutch_attempts_1v4' => $playerRoundEvent['clutch_attempts_1v4'] ?? 0,
                    'clutch_attempts_1v5' => $playerRoundEvent['clutch_attempts_1v5'] ?? 0,
                    'clutch_wins_1v1' => $playerRoundEvent['clutch_wins_1v1'] ?? 0,
                    'clutch_wins_1v2' => $playerRoundEvent['clutch_wins_1v2'] ?? 0,
                    'clutch_wins_1v3' => $playerRoundEvent['clutch_wins_1v3'] ?? 0,
                    'clutch_wins_1v4' => $playerRoundEvent['clutch_wins_1v4'] ?? 0,
                    'clutch_wins_1v5' => $playerRoundEvent['clutch_wins_1v5'] ?? 0,

                    'time_to_contact' => $playerRoundEvent['time_to_contact'] ?? 0,

                    // Economy fields
                    'is_eco' => $playerRoundEvent['is_eco'] ?? false,
                    'is_force_buy' => $playerRoundEvent['is_force_buy'] ?? false,
                    'is_full_buy' => $playerRoundEvent['is_full_buy'] ?? false,
                    'kills_vs_eco' => $playerRoundEvent['kills_vs_eco'] ?? 0,
                    'kills_vs_force_buy' => $playerRoundEvent['kills_vs_force_buy'] ?? 0,
                    'kills_vs_full_buy' => $playerRoundEvent['kills_vs_full_buy'] ?? 0,
                    'grenade_value_lost_on_death' => $playerRoundEvent['grenade_value_lost_on_death'] ?? 0,

                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // Use bulk insert for better performance
            PlayerRoundEvent::insert($records);
        }
    }

    private function createPlayerMatchEvent(GameMatch $match, array $playerMatchEvents): void
    {
        if (empty($playerMatchEvents)) {
            return;
        }

        $records = [];
        $now = now();

        foreach ($playerMatchEvents as $playerMatchEvent) {
            $records[] = [
                'match_id' => $match->id,
                'player_steam_id' => $playerMatchEvent['player_steam_id'],
                'kills' => $playerMatchEvent['kills'],
                'assists' => $playerMatchEvent['assists'],
                'deaths' => $playerMatchEvent['deaths'],
                'damage' => $playerMatchEvent['damage'],
                'adr' => $playerMatchEvent['adr'],
                'headshots' => $playerMatchEvent['headshots'],
                'first_kills' => $playerMatchEvent['first_kills'],
                'first_deaths' => $playerMatchEvent['first_deaths'],
                'average_round_time_of_death' => $playerMatchEvent['average_round_time_of_death'],
                'kills_with_awp' => $playerMatchEvent['kills_with_awp'],
                'damage_dealt' => $playerMatchEvent['damage_dealt'],
                'flashes_thrown' => $playerMatchEvent['flashes_thrown'],
                'fire_grenades_thrown' => $playerMatchEvent['fire_grenades_thrown'],
                'smokes_thrown' => $playerMatchEvent['smokes_thrown'],
                'hes_thrown' => $playerMatchEvent['hes_thrown'],
                'decoys_thrown' => $playerMatchEvent['decoys_thrown'],
                'friendly_flash_duration' => $playerMatchEvent['friendly_flash_duration'],
                'enemy_flash_duration' => $playerMatchEvent['enemy_flash_duration'],
                'friendly_players_affected' => $playerMatchEvent['friendly_players_affected'],
                'enemy_players_affected' => $playerMatchEvent['enemy_players_affected'],
                'flashes_leading_to_kills' => $playerMatchEvent['flashes_leading_to_kills'],
                'flashes_leading_to_deaths' => $playerMatchEvent['flashes_leading_to_deaths'],
                'average_grenade_effectiveness' => $playerMatchEvent['average_grenade_effectiveness'],
                'total_successful_trades' => $playerMatchEvent['total_successful_trades'],
                'total_possible_trades' => $playerMatchEvent['total_possible_trades'],
                'total_traded_deaths' => $playerMatchEvent['total_traded_deaths'],
                'total_possible_traded_deaths' => $playerMatchEvent['total_possible_traded_deaths'],
                'clutch_wins_1v1' => $playerMatchEvent['clutch_wins_1v1'],
                'clutch_wins_1v2' => $playerMatchEvent['clutch_wins_1v2'],
                'clutch_wins_1v3' => $playerMatchEvent['clutch_wins_1v3'],
                'clutch_wins_1v4' => $playerMatchEvent['clutch_wins_1v4'],
                'clutch_wins_1v5' => $playerMatchEvent['clutch_wins_1v5'],
                'clutch_attempts_1v1' => $playerMatchEvent['clutch_attempts_1v1'],
                'clutch_attempts_1v2' => $playerMatchEvent['clutch_attempts_1v2'],
                'clutch_attempts_1v3' => $playerMatchEvent['clutch_attempts_1v3'],
                'clutch_attempts_1v4' => $playerMatchEvent['clutch_attempts_1v4'],
                'clutch_attempts_1v5' => $playerMatchEvent['clutch_attempts_1v5'],
                'average_time_to_contact' => $playerMatchEvent['average_time_to_contact'],
                'kills_vs_eco' => $playerMatchEvent['kills_vs_eco'],
                'kills_vs_force_buy' => $playerMatchEvent['kills_vs_force_buy'],
                'kills_vs_full_buy' => $playerMatchEvent['kills_vs_full_buy'],
                'average_grenade_value_lost' => $playerMatchEvent['average_grenade_value_lost'],
                'matchmaking_rank' => $playerMatchEvent['matchmaking_rank'] ?? null,
                'rank_type' => $playerMatchEvent['rank_type'] ?? null,
                'rank_value' => $playerMatchEvent['rank_value'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        PlayerMatchEvent::insert($records);

        // Record player ranks
        $this->recordPlayerRanks($match, $playerMatchEvents);
    }

    private function recordPlayerRanks(GameMatch $match, array $playerMatchEvents): void
    {
        Log::info('Recording player ranks', [
            'match_id' => $match->id,
            'map' => $match->map,
            'total_events' => count($playerMatchEvents),
        ]);

        foreach ($playerMatchEvents as $playerMatchEvent) {
            Log::debug('Processing player match event for rank recording', [
                'player_steam_id' => $playerMatchEvent['player_steam_id'] ?? 'unknown',
                'rank_type' => $playerMatchEvent['rank_type'] ?? 'null',
                'rank_value' => $playerMatchEvent['rank_value'] ?? 'null',
                'matchmaking_rank' => $playerMatchEvent['matchmaking_rank'] ?? 'null',
            ]);

            // Only record rank if we have rank data
            if (empty($playerMatchEvent['rank_type']) || empty($playerMatchEvent['matchmaking_rank'])) {
                Log::debug('Skipping rank recording - missing rank data', [
                    'player_steam_id' => $playerMatchEvent['player_steam_id'] ?? 'unknown',
                    'has_rank_type' => ! empty($playerMatchEvent['rank_type']),
                    'has_matchmaking_rank' => ! empty($playerMatchEvent['matchmaking_rank']),
                ]);

                continue;
            }

            // Find the player by steam_id
            $player = Player::where('steam_id', $playerMatchEvent['player_steam_id'])->first();
            if (! $player) {
                continue;
            }

            // Use match upload time as the timestamp for rank tracking
            $rankTimestamp = $match->created_at ?? now();

            // Create or update player rank record
            $playerRank = PlayerRank::updateOrCreate(
                [
                    'player_id' => $player->id,
                    'rank_type' => $playerMatchEvent['rank_type'],
                    'map' => $match->map, // Include map for CS2 competitive mode
                    'created_at' => $rankTimestamp,
                ],
                [
                    'rank' => $playerMatchEvent['matchmaking_rank'],
                    'rank_value' => $playerMatchEvent['rank_value'] ?? 0,
                    'updated_at' => $rankTimestamp,
                ]
            );

            Log::info('Successfully recorded player rank', [
                'player_rank_id' => $playerRank->id,
                'player_id' => $player->id,
                'player_steam_id' => $playerMatchEvent['player_steam_id'],
                'rank_type' => $playerMatchEvent['rank_type'],
                'rank' => $playerMatchEvent['matchmaking_rank'],
                'rank_value' => $playerMatchEvent['rank_value'] ?? 0,
                'map' => $match->map,
            ]);
        }
    }

    private function updateMatchData(GameMatch $match, array $matchData): void
    {
        $updateData = [];

        // Update match type if provided
        if (isset($matchData['match_type'])) {
            $updateData['match_type'] = $matchData['match_type'];
        }

        // Update game mode if provided
        if (isset($matchData['game_mode']) && isset($matchData['game_mode']['mode'])) {
            $updateData['game_mode'] = $matchData['game_mode']['mode'];
        }

        // Update other match fields if provided
        if (isset($matchData['total_rounds'])) {
            $updateData['total_rounds'] = $matchData['total_rounds'];
        }
        if (isset($matchData['playback_ticks'])) {
            $updateData['playback_ticks'] = $matchData['playback_ticks'];
        }

        if (! empty($updateData)) {
            $match->update($updateData);

            Log::info('Updated match data', [
                'match_id' => $match->id,
                'updated_fields' => array_keys($updateData),
            ]);
        }
    }
}
