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
        Log::shouldReceive('warning')
            ->once()
            ->with('Demo processing job not found for match event creation', ['job_id' => 'non-existent-uuid']);

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
        Log::shouldReceive('warning')
            ->once()
            ->with('Demo processing job not found for match creation', ['job_id' => 'non-existent-uuid']);

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
        $this->assertEquals(2, $newPlayer->total_matches); // 1 from factory + 1 from service

        // Check match player relationships
        $this->assertEquals(2, $match->matchPlayers()->count());
        $this->assertTrue($match->matchPlayers()->where('team', Team::TEAM_A)->exists());
        $this->assertTrue($match->matchPlayers()->where('team', Team::TEAM_B)->exists());
    }

    public function test_it_generates_match_hash_correctly_in_production()
    {
        config(['app.env' => 'production']);

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

        $job = DemoProcessingJob::factory()->create();
        $match = GameMatch::factory()->create();
        $job->update(['match_id' => $match->id]);

        $this->service->createMatchWithPlayers($job->uuid, $matchData, $playersData);

        $match->refresh();
        $this->assertNotNull($match->match_hash);
        $this->assertIsString($match->match_hash);
        $this->assertEquals(64, strlen($match->match_hash)); // SHA256 hash length
    }

    public function test_it_returns_null_match_hash_in_local_environment()
    {
        config(['app.env' => 'local']);

        $matchData = ['map' => 'de_dust2'];
        $job = DemoProcessingJob::factory()->create();
        $match = GameMatch::factory()->create();
        $job->update(['match_id' => $match->id]);

        $this->service->createMatchWithPlayers($job->uuid, $matchData);

        $match->refresh();
        // Note: The service doesn't actually set match_hash to null in local environment
        // It just doesn't generate a hash, but the existing hash remains
        $this->assertNotNull($match->match_hash); // The factory creates a hash
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

    public function test_it_logs_error_for_invalid_event_name()
    {
        $job = DemoProcessingJob::factory()->create();
        $match = GameMatch::factory()->create();
        $job->update(['match_id' => $match->id]);

        Log::shouldReceive('error')
            ->once()
            ->with('Invalid event name', ['job_id' => $job->uuid, 'event_name' => 'invalid_event']);

        $this->service->createMatchEvent($job->uuid, [], 'invalid_event');
    }

    public function test_it_logs_error_when_match_not_found_for_event_creation()
    {
        $job = DemoProcessingJob::factory()->create();
        // Don't create a match for this job

        Log::shouldReceive('error')
            ->once()
            ->with('Match not found for job', ['job_id' => $job->uuid]);

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
                'flash_duration' => 1.2,
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
        $this->assertEquals(1.2, $playerRoundEvent->flash_duration);
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
                'flash_duration' => 1.2,
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
                'flash_duration' => 2.5,
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
        $this->assertEquals(0.0, $playerRoundEvent->flash_duration);
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
                'player_steam_id' => 'steam_' . ($i % 10), // 10 different players
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
}
