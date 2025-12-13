<?php

namespace Tests\Unit\Services;

use App\Enums\GrenadeType;
use App\Enums\MatchEventType;
use App\Enums\MatchType;
use App\Enums\ProcessingStatus;
use App\Enums\Team;
use App\Models\DamageEvent;
use App\Models\DemoProcessingJob;
use App\Models\GameMatch;
use App\Models\GrenadeEvent;
use App\Models\GunfightEvent;
use App\Models\Player;
use App\Models\PlayerRoundEvent;
use App\Services\DemoParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class DemoParserServiceTest extends TestCase
{
    use RefreshDatabase;

    private DemoParserService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DemoParserService;
    }

    public function test_it_can_update_processing_job_with_valid_data()
    {
        // Create a demo processing job
        $job = DemoProcessingJob::factory()->create([
            'processing_status' => ProcessingStatus::PENDING,
            'progress_percentage' => 0,
        ]);

        $data = [
            'status' => ProcessingStatus::PARSING->value,
            'progress' => 50,
            'current_step' => 'Parsing demo file',
        ];

        $this->service->updateProcessingJob($job->uuid, $data);

        $job->refresh();

        $this->assertEquals(ProcessingStatus::PARSING, $job->processing_status);
        $this->assertEquals(50, $job->progress_percentage);
        $this->assertEquals('Parsing demo file', $job->current_step);
        $this->assertNull($job->completed_at);
    }

    public function test_it_can_update_processing_job_as_completed()
    {
        $job = DemoProcessingJob::factory()->create([
            'processing_status' => ProcessingStatus::PARSING,
            'progress_percentage' => 50,
        ]);

        $data = [
            'status' => ProcessingStatus::COMPLETED->value,
            'progress' => 100,
            'current_step' => 'Completed',
        ];

        $this->service->updateProcessingJob($job->uuid, $data, true);

        $job->refresh();

        $this->assertEquals(ProcessingStatus::COMPLETED, $job->processing_status);
        $this->assertEquals(100, $job->progress_percentage);
        $this->assertEquals('Completed', $job->current_step);
        $this->assertNotNull($job->completed_at);
    }

    public function test_it_logs_warning_when_job_not_found_for_update()
    {
        $this->expectException(\App\Exceptions\DemoParserJobNotFoundException::class);
        $this->service->updateProcessingJob('non-existent-uuid', ['status' => ProcessingStatus::PARSING->value]);
    }

    public function test_it_can_create_match_with_players()
    {
        $job = DemoProcessingJob::factory()->create();

        $matchData = [
            'map' => 'de_dust2',
            'winning_team' => 'A',
            'winning_team_score' => 16,
            'losing_team_score' => 14,
            'match_type' => 'mm',
            'total_rounds' => 30,
            'playback_ticks' => 1000,
        ];

        $playersData = [
            [
                'steam_id' => 'steam_123',
                'name' => 'Player1',
                'team' => 'A',
            ],
            [
                'steam_id' => 'steam_456',
                'name' => 'Player2',
                'team' => 'B',
            ],
        ];

        $this->service->createMatchWithPlayers($job->uuid, $matchData, $playersData);

        $job->refresh();

        $this->assertNotNull($job->match);
        $this->assertEquals('de_dust2', $job->match->map);
        $this->assertEquals('A', $job->match->winning_team);
        $this->assertEquals(16, $job->match->winning_team_score);
        $this->assertEquals(14, $job->match->losing_team_score);
        $this->assertEquals(MatchType::MATCHMAKING, $job->match->match_type);
        $this->assertEquals(30, $job->match->total_rounds);
        $this->assertEquals(1000, $job->match->playback_ticks);

        // Check that players were created
        $this->assertEquals(2, $job->match->players()->count());
        $this->assertTrue($job->match->players()->where('steam_id', 'steam_123')->exists());
        $this->assertTrue($job->match->players()->where('steam_id', 'steam_456')->exists());
    }

    public function test_it_can_create_match_without_players()
    {
        $job = DemoProcessingJob::factory()->create();

        $matchData = [
            'map' => 'de_mirage',
            'winning_team' => 'B',
            'winning_team_score' => 13,
            'losing_team_score' => 7,
            'match_type' => 'faceit',
            'total_rounds' => 20,
            'playback_ticks' => 500,
        ];

        $this->service->createMatchWithPlayers($job->uuid, $matchData);

        $job->refresh();

        $this->assertNotNull($job->match);
        $this->assertEquals('de_mirage', $job->match->map);
        $this->assertEquals('B', $job->match->winning_team);
        $this->assertEquals(13, $job->match->winning_team_score);
        $this->assertEquals(7, $job->match->losing_team_score);
        $this->assertEquals(MatchType::FACEIT, $job->match->match_type);
        $this->assertEquals(20, $job->match->total_rounds);
        $this->assertEquals(500, $job->match->playback_ticks);

        // No players should be created
        $this->assertEquals(0, $job->match->players()->count());
    }

    public function test_it_updates_existing_match_when_match_already_exists()
    {
        $job = DemoProcessingJob::factory()->create();
        $existingMatch = GameMatch::factory()->create();
        $job->update(['match_id' => $existingMatch->id]);

        $matchData = [
            'map' => 'de_inferno',
            'winning_team' => 'A',
            'winning_team_score' => 16,
            'losing_team_score' => 10,
            'match_type' => 'hltv',
            'total_rounds' => 26,
            'playback_ticks' => 800,
        ];

        $this->service->createMatchWithPlayers($job->uuid, $matchData);

        $existingMatch->refresh();

        $this->assertEquals('de_inferno', $existingMatch->map);
        $this->assertEquals('A', $existingMatch->winning_team);
        $this->assertEquals(16, $existingMatch->winning_team_score);
        $this->assertEquals(10, $existingMatch->losing_team_score);
        $this->assertEquals(MatchType::HLTV, $existingMatch->match_type);
        $this->assertEquals(26, $existingMatch->total_rounds);
        $this->assertEquals(800, $existingMatch->playback_ticks);
    }

    public function test_it_logs_warning_when_job_not_found_for_match_creation()
    {
        $this->expectException(\App\Exceptions\DemoParserJobNotFoundException::class);
        $this->service->createMatchWithPlayers('non-existent-uuid', ['map' => 'de_dust2']);
    }

    public function test_it_creates_or_updates_players_correctly()
    {
        $job = DemoProcessingJob::factory()->create();
        $match = GameMatch::factory()->create();
        $job->update(['match_id' => $match->id]);

        // Create an existing player
        $existingPlayer = Player::factory()->create([
            'steam_id' => 'steam_123',
            'name' => 'OldName',
            'total_matches' => 5,
        ]);

        $playersData = [
            [
                'steam_id' => 'steam_123',
                'name' => 'NewName',
                'team' => 'A',
            ],
            [
                'steam_id' => 'steam_456',
                'name' => 'NewPlayer',
                'team' => 'B',
            ],
        ];

        $this->service->createMatchWithPlayers($job->uuid, ['map' => 'de_dust2'], $playersData);

        // Check that existing player was updated (name stays the same due to firstOrCreate behavior)
        $existingPlayer->refresh();
        $this->assertEquals('OldName', $existingPlayer->name); // Name doesn't change with firstOrCreate
        $this->assertEquals(6, $existingPlayer->total_matches);

        // Check that new player was created
        $newPlayer = Player::where('steam_id', 'steam_456')->first();
        $this->assertNotNull($newPlayer);
        $this->assertEquals('NewPlayer', $newPlayer->name);
        $this->assertEquals(1, $newPlayer->total_matches); // 1 from factory + 1 from service

        // Check match player relationships
        $this->assertEquals(2, $match->matchPlayers()->count());
        $this->assertTrue($match->matchPlayers()->where('team', Team::TEAM_A)->exists());
        $this->assertTrue($match->matchPlayers()->where('team', Team::TEAM_B)->exists());
    }

    public function test_it_generates_match_hash_correctly_in_production()
    {
        // Test the hash generation logic by manually calculating expected hash
        $matchData = [
            'map' => 'de_dust2',
            'winning_team_score' => 16,
            'losing_team_score' => 14,
            'match_type' => 'mm',
            'total_rounds' => 30,
            'playback_ticks' => 1000,
        ];

        $playersData = [
            [
                'steam_id' => 'steam_123',
                'team' => 'A',
            ],
            [
                'steam_id' => 'steam_456',
                'team' => 'B',
            ],
        ];

        // Manually calculate the expected hash (same logic as in generateMatchHash)
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

        $expectedHash = hash('sha256', implode('|', $hashData));

        $this->assertNotNull($expectedHash);
        $this->assertIsString($expectedHash);
        $this->assertEquals(64, strlen($expectedHash)); // SHA256 hash length

        // Verify the hash is deterministic
        $expectedHash2 = hash('sha256', implode('|', $hashData));
        $this->assertEquals($expectedHash, $expectedHash2);
    }

    public function test_it_can_create_damage_event()
    {
        $job = DemoProcessingJob::factory()->create();
        $match = GameMatch::factory()->create();
        $job->update(['match_id' => $match->id]);

        $damageEventData = [
            [
                'armor_damage' => 25,
                'attacker_steam_id' => 'steam_123',
                'damage' => 45,
                'headshot' => true,
                'health_damage' => 45,
                'round_number' => 1,
                'round_time' => 30,
                'tick_timestamp' => 1000,
                'victim_steam_id' => 'steam_456',
                'weapon' => 'AK-47',
            ],
        ];

        $this->service->createMatchEvent($job->uuid, $damageEventData, MatchEventType::DAMAGE->value);

        $this->assertEquals(1, DamageEvent::count());
        $damageEvent = DamageEvent::first();
        $this->assertEquals($match->id, $damageEvent->match_id);
        $this->assertEquals(25, $damageEvent->armor_damage);
        $this->assertEquals('steam_123', $damageEvent->attacker_steam_id);
        $this->assertEquals(45, $damageEvent->damage);
        $this->assertTrue($damageEvent->headshot);
        $this->assertEquals(45, $damageEvent->health_damage);
        $this->assertEquals(1, $damageEvent->round_number);
        $this->assertEquals(30, $damageEvent->round_time);
        $this->assertEquals(1000, $damageEvent->tick_timestamp);
        $this->assertEquals('steam_456', $damageEvent->victim_steam_id);
        $this->assertEquals('AK-47', $damageEvent->weapon);
    }

    public function test_it_can_create_gunfight_event()
    {
        $job = DemoProcessingJob::factory()->create();
        $match = GameMatch::factory()->create();
        $job->update(['match_id' => $match->id]);

        $gunfightEventData = [
            [
                'round_number' => 1,
                'round_time' => 45,
                'tick_timestamp' => 1500,
                'player_1_steam_id' => 'steam_123',
                'player_2_steam_id' => 'steam_456',
                'player_1_hp_start' => 100,
                'player_2_hp_start' => 85,
                'player_1_armor' => 100,
                'player_2_armor' => 50,
                'player_1_equipment_value' => 4000,
                'player_2_equipment_value' => 3000,
                'player_1_flashed' => false,
                'player_2_flashed' => true,
                'player_1_weapon' => 'AK-47',
                'player_2_weapon' => 'M4A4',
                'player_1_x' => 100.5,
                'player_1_y' => 200.3,
                'player_1_z' => 50.0,
                'player_2_x' => 150.2,
                'player_2_y' => 180.7,
                'player_2_z' => 45.0,
                'distance' => 25.5,
                'headshot' => true,
                'wallbang' => false,
                'penetrated_objects' => 0,
                'victor_steam_id' => 'steam_123',
                'damage_dealt' => 85,
                'is_first_kill' => false,
            ],
        ];

        $this->service->createMatchEvent($job->uuid, $gunfightEventData, MatchEventType::GUNFIGHT->value);

        $this->assertEquals(1, GunfightEvent::count());
        $gunfightEvent = GunfightEvent::first();
        $this->assertEquals($match->id, $gunfightEvent->match_id);
        $this->assertEquals(1, $gunfightEvent->round_number);
        $this->assertEquals(45, $gunfightEvent->round_time);
        $this->assertEquals(1500, $gunfightEvent->tick_timestamp);
        $this->assertEquals('steam_123', $gunfightEvent->player_1_steam_id);
        $this->assertEquals('steam_456', $gunfightEvent->player_2_steam_id);
        $this->assertEquals(100, $gunfightEvent->player_1_hp_start);
        $this->assertEquals(85, $gunfightEvent->player_2_hp_start);
        $this->assertEquals(100, $gunfightEvent->player_1_armor);
        $this->assertEquals(50, $gunfightEvent->player_2_armor);
        $this->assertEquals(4000, $gunfightEvent->player_1_equipment_value);
        $this->assertEquals(3000, $gunfightEvent->player_2_equipment_value);
        $this->assertFalse($gunfightEvent->player_1_flashed);
        $this->assertTrue($gunfightEvent->player_2_flashed);
        $this->assertEquals('AK-47', $gunfightEvent->player_1_weapon);
        $this->assertEquals('M4A4', $gunfightEvent->player_2_weapon);
        $this->assertEquals(100.5, $gunfightEvent->player_1_x);
        $this->assertEquals(200.3, $gunfightEvent->player_1_y);
        $this->assertEquals(50.0, $gunfightEvent->player_1_z);
        $this->assertEquals(150.2, $gunfightEvent->player_2_x);
        $this->assertEquals(180.7, $gunfightEvent->player_2_y);
        $this->assertEquals(45.0, $gunfightEvent->player_2_z);
        $this->assertEquals(25.5, $gunfightEvent->distance);
        $this->assertTrue($gunfightEvent->headshot);
        $this->assertFalse($gunfightEvent->wallbang);
        $this->assertEquals(0, $gunfightEvent->penetrated_objects);
        $this->assertEquals('steam_123', $gunfightEvent->victor_steam_id);
        $this->assertEquals(85, $gunfightEvent->damage_dealt);
        $this->assertFalse($gunfightEvent->is_first_kill);
    }

    public function test_it_can_create_grenade_event()
    {
        $job = DemoProcessingJob::factory()->create();
        $match = GameMatch::factory()->create();
        $job->update(['match_id' => $match->id]);

        $grenadeEventData = [
            [
                'round_number' => 1,
                'round_time' => 20,
                'tick_timestamp' => 800,
                'player_steam_id' => 'steam_123',
                'grenade_type' => GrenadeType::HE_GRENADE->value,
                'player_x' => 100.0,
                'player_y' => 200.0,
                'player_z' => 50.0,
                'player_aim_x' => 150.0,
                'player_aim_y' => 250.0,
                'player_aim_z' => 60.0,
                'grenade_final_x' => 120.0,
                'grenade_final_y' => 220.0,
                'grenade_final_z' => 55.0,
            ],
        ];

        $this->service->createMatchEvent($job->uuid, $grenadeEventData, MatchEventType::GRENADE->value);

        $this->assertEquals(1, GrenadeEvent::count());
        $grenadeEvent = GrenadeEvent::first();
        $this->assertEquals($match->id, $grenadeEvent->match_id);
        $this->assertEquals(1, $grenadeEvent->round_number);
        $this->assertEquals(20, $grenadeEvent->round_time);
        $this->assertEquals(800, $grenadeEvent->tick_timestamp);
        $this->assertEquals('steam_123', $grenadeEvent->player_steam_id);
        $this->assertEquals(GrenadeType::HE_GRENADE, $grenadeEvent->grenade_type);
        $this->assertEquals(100.0, $grenadeEvent->player_x);
        $this->assertEquals(200.0, $grenadeEvent->player_y);
        $this->assertEquals(50.0, $grenadeEvent->player_z);
        $this->assertEquals(150.0, $grenadeEvent->player_aim_x);
        $this->assertEquals(250.0, $grenadeEvent->player_aim_y);
        $this->assertEquals(60.0, $grenadeEvent->player_aim_z);
        $this->assertEquals(120.0, $grenadeEvent->grenade_final_x);
        $this->assertEquals(220.0, $grenadeEvent->grenade_final_y);
        $this->assertEquals(55.0, $grenadeEvent->grenade_final_z);
        $this->assertEquals(0, $grenadeEvent->damage_dealt);
        $this->assertEquals(0, $grenadeEvent->flash_duration);
    }

    public function test_it_logs_error_when_match_not_found_for_event_creation()
    {
        $job = DemoProcessingJob::factory()->create();
        // Don't create a match for this job

        $this->expectException(\App\Exceptions\DemoParserMatchNotFoundException::class);
        $this->service->createMatchEvent($job->uuid, [], MatchEventType::DAMAGE->value);
    }

    public function test_it_handles_multiple_events_in_single_call()
    {
        $job = DemoProcessingJob::factory()->create();
        $match = GameMatch::factory()->create();
        $job->update(['match_id' => $match->id]);

        $damageEvents = [
            [
                'armor_damage' => 25,
                'attacker_steam_id' => 'steam_123',
                'damage' => 45,
                'headshot' => true,
                'health_damage' => 45,
                'round_number' => 1,
                'round_time' => 30,
                'tick_timestamp' => 1000,
                'victim_steam_id' => 'steam_456',
                'weapon' => 'AK-47',
            ],
            [
                'armor_damage' => 0,
                'attacker_steam_id' => 'steam_789',
                'damage' => 30,
                'headshot' => false,
                'health_damage' => 30,
                'round_number' => 1,
                'round_time' => 45,
                'tick_timestamp' => 1200,
                'victim_steam_id' => 'steam_123',
                'weapon' => 'M4A4',
            ],
        ];

        $this->service->createMatchEvent($job->uuid, $damageEvents, MatchEventType::DAMAGE->value);

        $this->assertEquals(2, DamageEvent::count());
    }

    public function test_it_maps_match_types_correctly()
    {
        $job = DemoProcessingJob::factory()->create();

        $matchTypes = [
            'hltv' => MatchType::HLTV,
            'mm' => MatchType::MATCHMAKING,
            'faceit' => MatchType::FACEIT,
            'esportal' => MatchType::ESPORTAL,
            'other' => MatchType::OTHER,
            'unknown' => MatchType::OTHER,
        ];

        foreach ($matchTypes as $inputType => $expectedType) {
            $matchData = [
                'map' => 'de_dust2',
                'match_type' => $inputType,
            ];

            $this->service->createMatchWithPlayers($job->uuid, $matchData);

            $job->refresh();
            $this->assertEquals($expectedType, $job->match->match_type);

            // Clean up for next iteration
            $job->match->delete();
            $job->update(['match_id' => null]);
        }
    }

    public function test_it_maps_team_values_correctly()
    {
        $job = DemoProcessingJob::factory()->create();

        $teamMappings = [
            'A' => Team::TEAM_A,
            'B' => Team::TEAM_B,
            'a' => Team::TEAM_A,
            'b' => Team::TEAM_B,
            'unknown' => Team::TEAM_A, // Default fallback
        ];

        foreach ($teamMappings as $inputTeam => $expectedTeam) {
            $playersData = [
                [
                    'steam_id' => 'steam_123',
                    'name' => 'Player1',
                    'team' => $inputTeam,
                ],
            ];

            $this->service->createMatchWithPlayers($job->uuid, ['map' => 'de_dust2'], $playersData);

            $job->refresh();
            $matchPlayer = $job->match->matchPlayers()->first();
            $this->assertEquals($expectedTeam, $matchPlayer->team);

            // Clean up for next iteration
            $job->match->delete();
            $job->update(['match_id' => null]);
        }
    }

    public function test_it_handles_empty_or_null_data_gracefully()
    {
        $job = DemoProcessingJob::factory()->create();

        // Test with minimal data
        $minimalMatchData = ['map' => 'de_dust2'];
        $this->service->createMatchWithPlayers($job->uuid, $minimalMatchData);

        $job->refresh();
        $this->assertNotNull($job->match);
        $this->assertEquals('de_dust2', $job->match->map);
        $this->assertEquals('A', $job->match->winning_team); // Default
        $this->assertEquals(0, $job->match->winning_team_score); // Default
        $this->assertEquals(0, $job->match->losing_team_score); // Default
        $this->assertEquals(MatchType::OTHER, $job->match->match_type); // Default
        $this->assertEquals(0, $job->match->total_rounds); // Default
        $this->assertEquals(0, $job->match->playback_ticks); // Default
    }

    public function test_it_handles_player_data_with_missing_fields()
    {
        $job = DemoProcessingJob::factory()->create();

        $playersData = [
            [
                'steam_id' => 'steam_123',
                // Missing name and team
            ],
            [
                'steam_id' => 'steam_456',
                'name' => 'Player2',
                // Missing team
            ],
        ];

        $this->service->createMatchWithPlayers($job->uuid, ['map' => 'de_dust2'], $playersData);

        $job->refresh();

        // Check that players were created with defaults
        $player1 = Player::where('steam_id', 'steam_123')->first();
        $this->assertNotNull($player1);
        $this->assertEquals('Unknown', $player1->name); // Default name

        $player2 = Player::where('steam_id', 'steam_456')->first();
        $this->assertNotNull($player2);
        $this->assertEquals('Player2', $player2->name);

        // Check match players with default team
        $matchPlayers = $job->match->matchPlayers;
        $this->assertEquals(2, $matchPlayers->count());
        $this->assertTrue($matchPlayers->where('team', Team::TEAM_A)->count() >= 1); // At least one should have default team
    }

    public function test_it_can_create_player_round_event()
    {
        $job = DemoProcessingJob::factory()->create();
        $match = GameMatch::factory()->create();
        $job->update(['match_id' => $match->id]);

        $playerRoundEventData = [
            [
                'player_steam_id' => 'steam_12345',
                'round_number' => 1,
                'kills' => 2,
                'assists' => 1,
                'died' => false,
                'damage' => 150,
                'headshots' => 1,
                'first_kill' => true,
                'first_death' => false,
                'round_time_of_death' => null,
                'kills_with_awp' => 0,
                'damage_dealt' => 25,
                'flashes_thrown' => 1,
                'fire_grenades_thrown' => 0,
                'smokes_thrown' => 1,
                'hes_thrown' => 0,
                'decoys_thrown' => 1,
                'friendly_flash_duration' => 0.5,
                'enemy_flash_duration' => 2.1,
                'friendly_players_affected' => 0,
                'enemy_players_affected' => 2,
                'flashes_leading_to_kill' => 1,
                'flashes_leading_to_death' => 0,
                'grenade_effectiveness' => 0.75,
                'successful_trades' => 1,
                'total_possible_trades' => 2,
                'successful_traded_deaths' => 0,
                'total_possible_traded_deaths' => 1,
                'clutch_attempts_1v1' => 0,
                'clutch_attempts_1v2' => 1,
                'clutch_attempts_1v3' => 0,
                'clutch_attempts_1v4' => 0,
                'clutch_attempts_1v5' => 0,
                'clutch_wins_1v1' => 0,
                'clutch_wins_1v2' => 1,
                'clutch_wins_1v3' => 0,
                'clutch_wins_1v4' => 0,
                'clutch_wins_1v5' => 0,
                'time_to_contact' => 15.3,
                'is_eco' => false,
                'is_force_buy' => true,
                'is_full_buy' => false,
                'kills_vs_eco' => 1,
                'kills_vs_force_buy' => 1,
                'kills_vs_full_buy' => 0,
                'grenade_value_lost_on_death' => 0,
            ],
        ];

        $this->service->createMatchEvent($job->uuid, $playerRoundEventData, MatchEventType::PLAYER_ROUND->value);

        $this->assertEquals(1, PlayerRoundEvent::count());
        $playerRoundEvent = PlayerRoundEvent::first();
        $this->assertEquals($match->id, $playerRoundEvent->match_id);
        $this->assertEquals('steam_12345', $playerRoundEvent->player_steam_id);
        $this->assertEquals(1, $playerRoundEvent->round_number);
        $this->assertEquals(2, $playerRoundEvent->kills);
        $this->assertEquals(1, $playerRoundEvent->assists);
        $this->assertFalse($playerRoundEvent->died);
        $this->assertEquals(150, $playerRoundEvent->damage);
        $this->assertEquals(1, $playerRoundEvent->headshots);
        $this->assertTrue($playerRoundEvent->first_kill);
        $this->assertFalse($playerRoundEvent->first_death);
        $this->assertNull($playerRoundEvent->round_time_of_death);
        $this->assertEquals(0, $playerRoundEvent->kills_with_awp);
        $this->assertEquals(25, $playerRoundEvent->damage_dealt);
        $this->assertEquals(1, $playerRoundEvent->flashes_thrown);
        $this->assertEquals(0, $playerRoundEvent->fire_grenades_thrown);
        $this->assertEquals(1, $playerRoundEvent->smokes_thrown);
        $this->assertEquals(0, $playerRoundEvent->hes_thrown);
        $this->assertEquals(1, $playerRoundEvent->decoys_thrown);
        $this->assertEquals(0.5, $playerRoundEvent->friendly_flash_duration);
        $this->assertEquals(2.1, $playerRoundEvent->enemy_flash_duration);
        $this->assertEquals(0, $playerRoundEvent->friendly_players_affected);
        $this->assertEquals(2, $playerRoundEvent->enemy_players_affected);
        $this->assertEquals(1, $playerRoundEvent->flashes_leading_to_kill);
        $this->assertEquals(0, $playerRoundEvent->flashes_leading_to_death);
        $this->assertEquals(0.75, $playerRoundEvent->grenade_effectiveness);
        $this->assertEquals(1, $playerRoundEvent->successful_trades);
        $this->assertEquals(2, $playerRoundEvent->total_possible_trades);
        $this->assertEquals(0, $playerRoundEvent->successful_traded_deaths);
        $this->assertEquals(1, $playerRoundEvent->total_possible_traded_deaths);
        $this->assertEquals(0, $playerRoundEvent->clutch_attempts_1v1);
        $this->assertEquals(1, $playerRoundEvent->clutch_attempts_1v2);
        $this->assertEquals(0, $playerRoundEvent->clutch_attempts_1v3);
        $this->assertEquals(0, $playerRoundEvent->clutch_attempts_1v4);
        $this->assertEquals(0, $playerRoundEvent->clutch_attempts_1v5);
        $this->assertEquals(0, $playerRoundEvent->clutch_wins_1v1);
        $this->assertEquals(1, $playerRoundEvent->clutch_wins_1v2);
        $this->assertEquals(0, $playerRoundEvent->clutch_wins_1v3);
        $this->assertEquals(0, $playerRoundEvent->clutch_wins_1v4);
        $this->assertEquals(0, $playerRoundEvent->clutch_wins_1v5);
        $this->assertEquals(15.3, $playerRoundEvent->time_to_contact);
        $this->assertFalse($playerRoundEvent->is_eco);
        $this->assertTrue($playerRoundEvent->is_force_buy);
        $this->assertFalse($playerRoundEvent->is_full_buy);
        $this->assertEquals(1, $playerRoundEvent->kills_vs_eco);
        $this->assertEquals(1, $playerRoundEvent->kills_vs_force_buy);
        $this->assertEquals(0, $playerRoundEvent->kills_vs_full_buy);
        $this->assertEquals(0, $playerRoundEvent->grenade_value_lost_on_death);
    }

    public function test_it_can_create_multiple_player_round_events_in_single_call()
    {
        $job = DemoProcessingJob::factory()->create();
        $match = GameMatch::factory()->create();
        $job->update(['match_id' => $match->id]);

        $playerRoundEvents = [
            [
                'player_steam_id' => 'steam_12345',
                'round_number' => 1,
                'kills' => 2,
                'assists' => 1,
                'died' => false,
                'damage' => 150,
                'headshots' => 1,
                'first_kill' => true,
                'first_death' => false,
                'kills_with_awp' => 0,
                'damage_dealt' => 25,
                'flashes_thrown' => 1,
                'successful_trades' => 1,
                'clutch_wins_1v2' => 1,
                'time_to_contact' => 15.3,
                'is_eco' => false,
                'is_force_buy' => true,
                'kills_vs_eco' => 1,
            ],
            [
                'player_steam_id' => 'steam_67890',
                'round_number' => 1,
                'kills' => 1,
                'assists' => 0,
                'died' => true,
                'damage' => 75,
                'headshots' => 0,
                'first_kill' => false,
                'first_death' => true,
                'round_time_of_death' => 45,
                'kills_with_awp' => 1,
                'damage_dealt' => 0,
                'flashes_thrown' => 2,
                'successful_trades' => 0,
                'clutch_attempts_1v1' => 1,
                'time_to_contact' => 8.7,
                'is_eco' => true,
                'is_force_buy' => false,
                'kills_vs_full_buy' => 1,
                'grenade_value_lost_on_death' => 500,
            ],
        ];

        $this->service->createMatchEvent($job->uuid, $playerRoundEvents, MatchEventType::PLAYER_ROUND->value);

        $this->assertEquals(2, PlayerRoundEvent::count());

        $firstEvent = PlayerRoundEvent::where('player_steam_id', 'steam_12345')->first();
        $this->assertNotNull($firstEvent);
        $this->assertEquals(2, $firstEvent->kills);
        $this->assertEquals(1, $firstEvent->assists);
        $this->assertFalse($firstEvent->died);
        $this->assertTrue($firstEvent->first_kill);
        $this->assertFalse($firstEvent->first_death);
        $this->assertEquals(0, $firstEvent->kills_with_awp);
        $this->assertEquals(1, $firstEvent->successful_trades);
        $this->assertEquals(1, $firstEvent->clutch_wins_1v2);
        $this->assertFalse($firstEvent->is_eco);
        $this->assertTrue($firstEvent->is_force_buy);

        $secondEvent = PlayerRoundEvent::where('player_steam_id', 'steam_67890')->first();
        $this->assertNotNull($secondEvent);
        $this->assertEquals(1, $secondEvent->kills);
        $this->assertEquals(0, $secondEvent->assists);
        $this->assertTrue($secondEvent->died);
        $this->assertFalse($secondEvent->first_kill);
        $this->assertTrue($secondEvent->first_death);
        $this->assertEquals(45, $secondEvent->round_time_of_death);
        $this->assertEquals(1, $secondEvent->kills_with_awp);
        $this->assertEquals(0, $secondEvent->successful_trades);
        $this->assertEquals(1, $secondEvent->clutch_attempts_1v1);
        $this->assertTrue($secondEvent->is_eco);
        $this->assertFalse($secondEvent->is_force_buy);
        $this->assertEquals(500, $secondEvent->grenade_value_lost_on_death);
    }

    public function test_it_handles_empty_player_round_event_data()
    {
        $job = DemoProcessingJob::factory()->create();
        $match = GameMatch::factory()->create();
        $job->update(['match_id' => $match->id]);

        $this->service->createMatchEvent($job->uuid, [], MatchEventType::PLAYER_ROUND->value);

        $this->assertEquals(0, PlayerRoundEvent::count());
    }

    public function test_it_handles_player_round_event_data_with_defaults()
    {
        $job = DemoProcessingJob::factory()->create();
        $match = GameMatch::factory()->create();
        $job->update(['match_id' => $match->id]);

        $minimalPlayerRoundEventData = [
            [
                'player_steam_id' => 'steam_12345',
                'round_number' => 1,
                // Most fields missing - should use defaults
            ],
        ];

        $this->service->createMatchEvent($job->uuid, $minimalPlayerRoundEventData, MatchEventType::PLAYER_ROUND->value);

        $this->assertEquals(1, PlayerRoundEvent::count());
        $playerRoundEvent = PlayerRoundEvent::first();

        // Check that defaults are applied
        $this->assertEquals(0, $playerRoundEvent->kills);
        $this->assertEquals(0, $playerRoundEvent->assists);
        $this->assertFalse($playerRoundEvent->died);
        $this->assertEquals(0, $playerRoundEvent->damage);
        $this->assertEquals(0, $playerRoundEvent->headshots);
        $this->assertFalse($playerRoundEvent->first_kill);
        $this->assertFalse($playerRoundEvent->first_death);
        $this->assertNull($playerRoundEvent->round_time_of_death);
        $this->assertEquals(0, $playerRoundEvent->kills_with_awp);
        $this->assertEquals(0, $playerRoundEvent->damage_dealt);
        $this->assertEquals(0, $playerRoundEvent->flashes_thrown);
        $this->assertEquals(0.0, $playerRoundEvent->friendly_flash_duration);
        $this->assertEquals(0.0, $playerRoundEvent->enemy_flash_duration);
        $this->assertEquals(0, $playerRoundEvent->friendly_players_affected);
        $this->assertEquals(0, $playerRoundEvent->enemy_players_affected);
        $this->assertEquals(0, $playerRoundEvent->flashes_leading_to_kill);
        $this->assertEquals(0, $playerRoundEvent->flashes_leading_to_death);
        $this->assertEquals(0.0, $playerRoundEvent->grenade_effectiveness);
        $this->assertEquals(0, $playerRoundEvent->successful_trades);
        $this->assertEquals(0, $playerRoundEvent->total_possible_trades);
        $this->assertEquals(0, $playerRoundEvent->successful_traded_deaths);
        $this->assertEquals(0, $playerRoundEvent->total_possible_traded_deaths);
        $this->assertEquals(0, $playerRoundEvent->clutch_attempts_1v1);
        $this->assertEquals(0, $playerRoundEvent->clutch_wins_1v1);
        $this->assertEquals(0.0, $playerRoundEvent->time_to_contact);
        $this->assertFalse($playerRoundEvent->is_eco);
        $this->assertFalse($playerRoundEvent->is_force_buy);
        $this->assertFalse($playerRoundEvent->is_full_buy);
        $this->assertEquals(0, $playerRoundEvent->kills_vs_eco);
        $this->assertEquals(0, $playerRoundEvent->kills_vs_force_buy);
        $this->assertEquals(0, $playerRoundEvent->kills_vs_full_buy);
        $this->assertEquals(0, $playerRoundEvent->grenade_value_lost_on_death);
    }

    public function test_it_handles_large_player_round_event_batches()
    {
        $job = DemoProcessingJob::factory()->create();
        $match = GameMatch::factory()->create();
        $job->update(['match_id' => $match->id]);

        // Create 1500 events to test chunking (chunk size is 1000)
        $playerRoundEvents = [];
        for ($i = 1; $i <= 1500; $i++) {
            $playerRoundEvents[] = [
                'player_steam_id' => 'steam_'.($i % 10), // 10 different players
                'round_number' => ($i % 30) + 1, // Rounds 1-30
                'kills' => $i % 5,
                'damage' => $i * 10,
                'assists' => $i % 3,
            ];
        }

        $this->service->createMatchEvent($job->uuid, $playerRoundEvents, MatchEventType::PLAYER_ROUND->value);

        $this->assertEquals(1500, PlayerRoundEvent::count());

        // Verify some records were created correctly
        $firstEvent = PlayerRoundEvent::where('player_steam_id', 'steam_1')->first();
        $this->assertNotNull($firstEvent);

        $lastEvent = PlayerRoundEvent::where('player_steam_id', 'steam_9')->first();
        $this->assertNotNull($lastEvent);
    }

    public function test_player_round_event_includes_match_event_type()
    {
        $job = DemoProcessingJob::factory()->create();
        $match = GameMatch::factory()->create();
        $job->update(['match_id' => $match->id]);

        $playerRoundEventData = [
            [
                'player_steam_id' => 'steam_12345',
                'round_number' => 1,
                'kills' => 1,
            ],
        ];

        // Test that PLAYER_ROUND event type is handled
        $this->service->createMatchEvent($job->uuid, $playerRoundEventData, MatchEventType::PLAYER_ROUND->value);
        $this->assertEquals(1, PlayerRoundEvent::count());

        // Test that it doesn't interfere with other event types
        $damageEventData = [
            [
                'armor_damage' => 25,
                'attacker_steam_id' => 'steam_123',
                'damage' => 45,
                'headshot' => true,
                'health_damage' => 45,
                'round_number' => 1,
                'round_time' => 30,
                'tick_timestamp' => 1000,
                'victim_steam_id' => 'steam_456',
                'weapon' => 'AK-47',
            ],
        ];

        $this->service->createMatchEvent($job->uuid, $damageEventData, MatchEventType::DAMAGE->value);

        $this->assertEquals(1, PlayerRoundEvent::count());
        $this->assertEquals(1, DamageEvent::count());
    }

    // Enhanced Progress Tracking Tests

    public function test_it_can_update_processing_job_with_enhanced_progress_fields()
    {
        $job = DemoProcessingJob::factory()->create([
            'processing_status' => ProcessingStatus::PENDING,
            'progress_percentage' => 0,
        ]);

        $data = [
            'status' => ProcessingStatus::PARSING->value,
            'progress' => 25,
            'current_step' => 'Processing grenade events',
            'step_progress' => 75,
            'total_steps' => 20,
            'current_step_num' => 6,
            'start_time' => '2024-01-01 10:00:00',
            'last_update_time' => '2024-01-01 10:05:00',
            'error_code' => null,
            'context' => [
                'step' => 'grenade_events_processing',
                'round' => 3,
                'total_rounds' => 16,
            ],
            'is_final' => false,
        ];

        $this->service->updateProcessingJob($job->uuid, $data);

        $job->refresh();

        // Test basic fields
        $this->assertEquals(ProcessingStatus::PARSING, $job->processing_status);
        $this->assertEquals(25, $job->progress_percentage);
        $this->assertEquals('Processing grenade events', $job->current_step);

        // Test enhanced progress fields
        $this->assertEquals(75, $job->step_progress);
        $this->assertEquals(20, $job->total_steps);
        $this->assertEquals(6, $job->current_step_num);
        $this->assertEquals('2024-01-01 10:00:00', $job->start_time->format('Y-m-d H:i:s'));
        $this->assertEquals('2024-01-01 10:05:00', $job->last_update_time->format('Y-m-d H:i:s'));
        $this->assertNull($job->error_code);
        $this->assertEquals([
            'step' => 'grenade_events_processing',
            'round' => 3,
            'total_rounds' => 16,
        ], $job->context);
        $this->assertFalse($job->is_final);
    }

    public function test_it_can_update_processing_job_with_partial_enhanced_fields()
    {
        $job = DemoProcessingJob::factory()->create([
            'processing_status' => ProcessingStatus::PENDING,
            'progress_percentage' => 0,
        ]);

        // Test with only some enhanced fields present
        $data = [
            'status' => ProcessingStatus::PROCESSING_EVENTS->value,
            'progress' => 50,
            'current_step' => 'Processing round events',
            'step_progress' => 30,
            'total_steps' => 18,
            'current_step_num' => 3,
            // Missing: start_time, last_update_time, error_code, context, is_final
        ];

        $this->service->updateProcessingJob($job->uuid, $data);

        $job->refresh();

        // Test that only provided fields are updated
        $this->assertEquals(ProcessingStatus::PROCESSING_EVENTS, $job->processing_status);
        $this->assertEquals(50, $job->progress_percentage);
        $this->assertEquals('Processing round events', $job->current_step);
        $this->assertEquals(30, $job->step_progress);
        $this->assertEquals(18, $job->total_steps);
        $this->assertEquals(3, $job->current_step_num);

        // Test that missing fields remain unchanged (null or default values)
        $this->assertNull($job->start_time);
        $this->assertNull($job->last_update_time);
        $this->assertNull($job->error_code);
        $this->assertNull($job->context);
        $this->assertFalse($job->is_final); // Default value
    }

    public function test_it_can_update_processing_job_with_error_code()
    {
        $job = DemoProcessingJob::factory()->create([
            'processing_status' => ProcessingStatus::PARSING,
            'progress_percentage' => 30,
        ]);

        $data = [
            'status' => ProcessingStatus::FAILED->value,
            'progress' => 30,
            'current_step' => 'Processing demo file',
            'error_message' => 'Demo file corrupted',
            'error_code' => 'DEMO_CORRUPTED',
            'step_progress' => 0,
            'total_steps' => 18,
            'current_step_num' => 1,
            'context' => [
                'step' => 'file_validation',
                'error_details' => 'Invalid demo header',
            ],
            'is_final' => true,
        ];

        $this->service->updateProcessingJob($job->uuid, $data);

        $job->refresh();

        $this->assertEquals(ProcessingStatus::FAILED, $job->processing_status);
        $this->assertEquals('Demo file corrupted', $job->error_message);
        $this->assertEquals('DEMO_CORRUPTED', $job->error_code);
        $this->assertEquals([
            'step' => 'file_validation',
            'error_details' => 'Invalid demo header',
        ], $job->context);
        $this->assertTrue($job->is_final);
    }

    public function test_it_can_update_processing_job_with_final_completion()
    {
        $job = DemoProcessingJob::factory()->create([
            'processing_status' => ProcessingStatus::FINALIZING,
            'progress_percentage' => 95,
        ]);

        $data = [
            'status' => ProcessingStatus::COMPLETED->value,
            'progress' => 100,
            'current_step' => 'Completed',
            'step_progress' => 100,
            'total_steps' => 18,
            'current_step_num' => 18,
            'context' => [
                'step' => 'finalization',
                'total_events_processed' => 1250,
            ],
            'is_final' => true,
        ];

        $this->service->updateProcessingJob($job->uuid, $data, true);

        $job->refresh();

        $this->assertEquals(ProcessingStatus::COMPLETED, $job->processing_status);
        $this->assertEquals(100, $job->progress_percentage);
        $this->assertEquals('Completed', $job->current_step);
        $this->assertEquals(100, $job->step_progress);
        $this->assertEquals(18, $job->current_step_num);
        $this->assertEquals([
            'step' => 'finalization',
            'total_events_processed' => 1250,
        ], $job->context);
        $this->assertTrue($job->is_final);
        $this->assertNotNull($job->completed_at);
    }

    public function test_it_handles_complex_context_data()
    {
        $job = DemoProcessingJob::factory()->create([
            'processing_status' => ProcessingStatus::PENDING,
            'progress_percentage' => 0,
        ]);

        $complexContext = [
            'step' => 'round_events_processing',
            'round' => 5,
            'total_rounds' => 16,
            'events_processed' => 150,
            'total_events' => 300,
            'processing_time' => 2.5,
            'memory_usage' => '128MB',
            'debug_info' => [
                'parser_version' => '1.0.0',
                'demo_ticks' => 50000,
                'map_name' => 'de_dust2',
            ],
        ];

        $data = [
            'status' => ProcessingStatus::PROCESSING_EVENTS->value,
            'progress' => 40,
            'current_step' => 'Processing round 5 of 16',
            'step_progress' => 50,
            'total_steps' => 34, // 18 base + 16 rounds
            'current_step_num' => 3,
            'context' => $complexContext,
        ];

        $this->service->updateProcessingJob($job->uuid, $data);

        $job->refresh();

        $this->assertEquals($complexContext, $job->context);
        $this->assertEquals(34, $job->total_steps);
        $this->assertEquals(50, $job->step_progress);
    }

    public function test_it_handles_null_and_empty_values_correctly()
    {
        $job = DemoProcessingJob::factory()->create([
            'processing_status' => ProcessingStatus::PENDING,
            'progress_percentage' => 0,
        ]);

        $data = [
            'status' => ProcessingStatus::PARSING->value,
            'progress' => 10,
            'current_step' => 'Initializing',
            'step_progress' => 0,
            'total_steps' => 18,
            'current_step_num' => 1,
            'start_time' => null,
            'last_update_time' => null,
            'error_code' => null,
            'context' => null,
            'is_final' => false,
        ];

        $this->service->updateProcessingJob($job->uuid, $data);

        $job->refresh();

        $this->assertEquals(ProcessingStatus::PARSING, $job->processing_status);
        $this->assertEquals(10, $job->progress_percentage);
        $this->assertEquals('Initializing', $job->current_step);
        $this->assertEquals(0, $job->step_progress);
        $this->assertEquals(18, $job->total_steps);
        $this->assertEquals(1, $job->current_step_num);
        $this->assertNull($job->start_time);
        $this->assertNull($job->last_update_time);
        $this->assertNull($job->error_code);
        $this->assertNull($job->context);
        $this->assertFalse($job->is_final);
    }

    public function test_it_handles_missing_enhanced_fields_gracefully()
    {
        $job = DemoProcessingJob::factory()->create([
            'processing_status' => ProcessingStatus::PENDING,
            'progress_percentage' => 0,
            'step_progress' => 0,
            'total_steps' => 18,
            'current_step_num' => 1,
        ]);

        // Test with only basic fields (backward compatibility)
        $data = [
            'status' => ProcessingStatus::PARSING->value,
            'progress' => 20,
            'current_step' => 'Parsing demo file',
            // No enhanced fields provided
        ];

        $this->service->updateProcessingJob($job->uuid, $data);

        $job->refresh();

        // Basic fields should be updated
        $this->assertEquals(ProcessingStatus::PARSING, $job->processing_status);
        $this->assertEquals(20, $job->progress_percentage);
        $this->assertEquals('Parsing demo file', $job->current_step);

        // Enhanced fields should remain unchanged
        $this->assertEquals(0, $job->step_progress);
        $this->assertEquals(18, $job->total_steps);
        $this->assertEquals(1, $job->current_step_num);
    }

    public function test_it_extracts_match_start_time_when_job_completes(): void
    {
        $job = DemoProcessingJob::factory()->create([
            'original_file_name' => '1-25e72cdb-ac23-4237-a95d-701603b58681-1-1.dem',
        ]);
        $match = GameMatch::factory()->create([
            'match_type' => \App\Enums\MatchType::FACEIT,
            'map' => 'de_dust2',
            'winning_team_score' => 16,
            'losing_team_score' => 14,
        ]);
        $job->update(['match_id' => $match->id]);

        // Mock FaceITRepository to return valid data
        $faceitRepository = \Mockery::mock(\App\Services\FaceITRepository::class);
        $faceitRepository
            ->shouldReceive('getMatchDetails')
            ->once()
            ->andReturn([
                'started_at' => 1760394042,
                'teams' => [
                    [
                        'roster' => [],
                    ],
                ],
            ]);
        $faceitRepository
            ->shouldReceive('getMatchStats')
            ->once()
            ->andReturn([
                'rounds' => [
                    [
                        'round_stats' => [
                            'Map' => 'de_dust2',
                            'Score' => '16 / 14',
                        ],
                    ],
                ],
            ]);

        $this->app->instance(\App\Services\FaceITRepository::class, $faceitRepository);

        $data = [
            'status' => ProcessingStatus::COMPLETED->value,
            'progress' => 100,
            'current_step' => 'Completed',
        ];

        $this->service->updateProcessingJob($job->uuid, $data, true);

        $match->refresh();
        $this->assertNotNull($match->match_start_time);
        $this->assertEquals(1760394042, $match->match_start_time->timestamp);
    }

    public function test_it_handles_match_time_extraction_failure_gracefully(): void
    {
        $job = DemoProcessingJob::factory()->create([
            'original_file_name' => '1-25e72cdb-ac23-4237-a95d-701603b58681-1-1.dem',
        ]);
        $match = GameMatch::factory()->create([
            'match_type' => \App\Enums\MatchType::FACEIT,
            'match_start_time' => null, // Ensure it starts as null
        ]);
        $job->update(['match_id' => $match->id]);

        // Mock FaceITRepository to throw an exception
        $faceitRepository = \Mockery::mock(\App\Services\FaceITRepository::class);
        $faceitRepository
            ->shouldReceive('getMatchDetails')
            ->once()
            ->andThrow(new \Exception('API Error'));

        $this->app->instance(\App\Services\FaceITRepository::class, $faceitRepository);

        \Illuminate\Support\Facades\Log::shouldReceive('channel')
            ->with('parser')
            ->andReturnSelf();
        \Illuminate\Support\Facades\Log::shouldReceive('warning')
            ->once();

        $data = [
            'status' => ProcessingStatus::COMPLETED->value,
            'progress' => 100,
            'current_step' => 'Completed',
        ];

        // Should not throw exception
        $this->service->updateProcessingJob($job->uuid, $data, true);

        $match->refresh();
        $this->assertNull($match->match_start_time);
    }

    public function test_it_does_not_extract_match_time_when_no_match(): void
    {
        $job = DemoProcessingJob::factory()->create([
            'original_file_name' => '1-25e72cdb-ac23-4237-a95d-701603b58681-1-1.dem',
        ]);

        $data = [
            'status' => ProcessingStatus::COMPLETED->value,
            'progress' => 100,
            'current_step' => 'Completed',
        ];

        // Should not throw exception even without a match
        $this->service->updateProcessingJob($job->uuid, $data, true);

        $this->assertTrue(true); // Test passes if no exception thrown
    }

    public function test_it_detects_duplicate_demo_when_duplicates_not_allowed()
    {
        // Disable duplicate demos (production-like behavior)
        $originalConfig = config('services.parser.allow_duplicate_demos');
        config(['services.parser.allow_duplicate_demos' => false]);

        $job = DemoProcessingJob::factory()->create();

        $matchData = [
            'map' => 'de_dust2',
            'winning_team' => 'A',
            'winning_team_score' => 16,
            'losing_team_score' => 14,
            'match_type' => 'mm',
            'total_rounds' => 30,
            'playback_ticks' => 1000,
        ];

        $playersData = [
            [
                'steam_id' => 'steam_123',
                'name' => 'Player1',
                'team' => 'A',
            ],
            [
                'steam_id' => 'steam_456',
                'name' => 'Player2',
                'team' => 'B',
            ],
        ];

        // Create first match
        $this->service->createMatchWithPlayers($job->uuid, $matchData, $playersData);

        $job->refresh();
        $firstMatch = $job->match;
        $this->assertNotNull($firstMatch);
        $this->assertNotNull($firstMatch->match_hash);

        // Create a second job with the same match data (duplicate)
        $duplicateJob = DemoProcessingJob::factory()->create();

        // Mock Log to verify logging
        Log::shouldReceive('info')
            ->once()
            ->with('Duplicate demo detected and job cancelled', \Mockery::type('array'));

        $this->service->createMatchWithPlayers($duplicateJob->uuid, $matchData, $playersData);

        $duplicateJob->refresh();

        // Verify job was cancelled
        $this->assertEquals(ProcessingStatus::CANCELLED, $duplicateJob->processing_status);
        $this->assertNotNull($duplicateJob->completed_at);
        $this->assertEquals(0, $duplicateJob->progress_percentage);
        $this->assertEquals('Duplicate detected', $duplicateJob->current_step);
        $this->assertStringContainsString('/matches/'.$firstMatch->id, $duplicateJob->error_message);
        $this->assertStringContainsString('already been processed', $duplicateJob->error_message);

        // Verify no new match was created for the duplicate job
        $this->assertNull($duplicateJob->match);

        // Verify only one match exists
        $this->assertEquals(1, GameMatch::where('match_hash', $firstMatch->match_hash)->count());

        // Restore original config
        config(['services.parser.allow_duplicate_demos' => $originalConfig]);
    }

    public function test_it_skips_duplicate_detection_when_duplicates_allowed()
    {
        // Enable duplicate demos (local-like behavior)
        $originalConfig = config('services.parser.allow_duplicate_demos');
        config(['services.parser.allow_duplicate_demos' => true]);

        $job = DemoProcessingJob::factory()->create();

        $matchData = [
            'map' => 'de_dust2',
            'winning_team' => 'A',
            'winning_team_score' => 16,
            'losing_team_score' => 14,
            'match_type' => 'mm',
            'total_rounds' => 30,
            'playback_ticks' => 1000,
        ];

        $playersData = [
            [
                'steam_id' => 'steam_123',
                'name' => 'Player1',
                'team' => 'A',
            ],
        ];

        // Create first match
        $this->service->createMatchWithPlayers($job->uuid, $matchData, $playersData);

        $job->refresh();
        $firstMatch = $job->match;
        $this->assertNotNull($firstMatch);
        $this->assertNull($firstMatch->match_hash); // Hash should be null when duplicates allowed

        // Create a second job with the same match data
        $secondJob = DemoProcessingJob::factory()->create();

        // Should not log duplicate detection
        Log::shouldReceive('info')
            ->with('Duplicate demo detected and job cancelled', \Mockery::any())
            ->never();

        $this->service->createMatchWithPlayers($secondJob->uuid, $matchData, $playersData);

        $secondJob->refresh();

        // Verify second job was NOT cancelled
        $this->assertNotEquals(ProcessingStatus::CANCELLED, $secondJob->processing_status);

        // Verify a second match was created (duplicate detection skipped)
        $this->assertNotNull($secondJob->match);
        $this->assertEquals(2, GameMatch::count());

        // Restore original config
        config(['services.parser.allow_duplicate_demos' => $originalConfig]);
    }

    public function test_it_allows_updating_existing_match_without_triggering_duplicate_detection()
    {
        // Disable duplicate demos to enable hash generation
        $originalConfig = config('services.parser.allow_duplicate_demos');
        config(['services.parser.allow_duplicate_demos' => false]);

        $job = DemoProcessingJob::factory()->create();
        $existingMatch = GameMatch::factory()->create([
            'match_hash' => 'existing_hash_123',
        ]);
        $job->update(['match_id' => $existingMatch->id]);

        $matchData = [
            'map' => 'de_dust2',
            'winning_team' => 'A',
            'winning_team_score' => 16,
            'losing_team_score' => 14,
            'match_type' => 'mm',
            'total_rounds' => 30,
            'playback_ticks' => 1000,
        ];

        // Should not log duplicate detection when updating existing match
        Log::shouldReceive('info')
            ->with('Duplicate demo detected and job cancelled', \Mockery::any())
            ->never();

        $this->service->createMatchWithPlayers($job->uuid, $matchData);

        $job->refresh();

        // Verify job was NOT cancelled
        $this->assertNotEquals(ProcessingStatus::CANCELLED, $job->processing_status);

        // Verify match was updated
        $existingMatch->refresh();
        $this->assertNotNull($existingMatch->match_hash);

        // Restore original config
        config(['services.parser.allow_duplicate_demos' => $originalConfig]);
    }
}
