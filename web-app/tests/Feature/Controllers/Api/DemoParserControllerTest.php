<?php

namespace Tests\Feature;

use App\Enums\ProcessingStatus;
use App\Models\DemoProcessingJob;
use App\Models\GameMatch;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoParserControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.api_key' => 'test-api-key']);
    }

    public function test_handle_event_with_valid_gunfight_data()
    {
        $job = DemoProcessingJob::factory()->create();
        $match = GameMatch::factory()->create();
        $job->update(['match_id' => $match->id]);
        $jobId = $job->uuid;
        $eventName = 'gunfight';

        $payload = [
            'batch_index' => 1,
            'is_last' => false,
            'total_batches' => 3,
            'data' => [
                [
                    'round_number' => 1,
                    'round_time' => 30,
                    'tick_timestamp' => 12345,
                    'player_1_steam_id' => 'steam_123',
                    'player_2_steam_id' => 'steam_456',
                    'player_1_hp_start' => 100,
                    'player_2_hp_start' => 100,
                    'player_1_armor' => 100,
                    'player_2_armor' => 0,
                    'player_1_flashed' => false,
                    'player_2_flashed' => false,
                    'player_1_weapon' => 'ak47',
                    'player_2_weapon' => 'm4a1',
                    'player_1_equipment_value' => 2700,
                    'player_2_equipment_value' => 3100,
                    'player_1_x' => 100.5,
                    'player_1_y' => 200.3,
                    'player_1_z' => 50.0,
                    'player_2_x' => 150.2,
                    'player_2_y' => 180.7,
                    'player_2_z' => 50.0,
                    'distance' => 50.0,
                    'headshot' => true,
                    'wallbang' => false,
                    'penetrated_objects' => 0,
                    'victor_steam_id' => 'steam_123',
                    'damage_dealt' => 100,
                ],
            ],
        ];

        $response = $this->withHeaders([
            'X-API-Key' => 'test-api-key',
            'Content-Type' => 'application/json',
        ])->postJson("/api/job/{$jobId}/event/{$eventName}", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Event processed successfully',
                'job_id' => $jobId,
                'event_name' => $eventName,
            ]);
    }

    public function test_handle_event_with_valid_round_data()
    {
        $job = DemoProcessingJob::factory()->create();
        $match = GameMatch::factory()->create();
        $job->update(['match_id' => $match->id]);
        $jobId = $job->uuid;
        $eventName = 'player-round';

        $payload = [
            'data' => [
                [
                    'player_steam_id' => 'steam_123',
                    'round_number' => 1,
                    'tick_timestamp' => 12345,
                    'event_type' => 'start',
                ],
            ],
        ];

        $response = $this->withHeaders([
            'X-API-Key' => 'test-api-key',
            'Content-Type' => 'application/json',
        ])->postJson("/api/job/{$jobId}/event/{$eventName}", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'job_id' => $jobId,
                'event_name' => $eventName,
            ]);
    }

    public function test_handle_event_without_api_key()
    {
        $jobId = 'test-job-999';
        $eventName = 'gunfight';

        $payload = [
            'data' => [],
        ];

        $response = $this->postJson("/api/job/{$jobId}/event/{$eventName}", $payload);

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'API key is required',
            ]);
    }

    public function test_handle_event_with_invalid_api_key()
    {
        $jobId = 'test-job-888';
        $eventName = 'gunfight';

        $payload = [
            'data' => [],
        ];

        $response = $this->withHeaders([
            'X-API-Key' => 'invalid-api-key',
            'Content-Type' => 'application/json',
        ])->postJson("/api/job/{$jobId}/event/{$eventName}", $payload);

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'Invalid API key',
            ]);
    }

    public function test_progress_callback_with_match_and_players_data(): void
    {
        $jobId = 'test-job-123';

        // Create a demo processing job first
        DemoProcessingJob::create([
            'uuid' => $jobId,
            'processing_status' => ProcessingStatus::QUEUED,
            'progress_percentage' => 0,
            'current_step' => 'Job queued',
        ]);

        $payload = [
            'job_id' => $jobId,
            'status' => 'SendingMetadata',
            'progress' => 90,
            'current_step' => 'Sending match metadata',
            'match' => [
                'map' => 'de_dust2',
                'winning_team_score' => 16,
                'losing_team_score' => 14,
                'match_type' => 'mm',
                'match_start_time' => '2025-08-10T01:00:00Z',
                'end_timestamp' => '2025-08-10T02:00:00Z',
                'total_rounds' => 30,
            ],
            'players' => [
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
            ],
        ];

        $response = $this->withHeaders([
            'X-API-Key' => 'test-api-key',
            'Content-Type' => 'application/json',
        ])->postJson('/api/job/callback/progress', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Progress update received',
                'job_id' => $jobId,
            ]);

        // Verify the match was created
        $this->assertDatabaseHas('matches', [
            'map' => 'de_dust2',
            'winning_team_score' => 16,
            'losing_team_score' => 14,
            'match_type' => 'mm',
            'total_rounds' => 30,
        ]);

        // Verify the players were created
        $this->assertDatabaseHas('players', [
            'steam_id' => 'steam_123',
            'name' => 'Player1',
        ]);

        $this->assertDatabaseHas('players', [
            'steam_id' => 'steam_456',
            'name' => 'Player2',
        ]);

        // Verify the match-player relationships were created
        $match = GameMatch::where('map', 'de_dust2')->first();
        $player1 = Player::where('steam_id', 'steam_123')->first();
        $player2 = Player::where('steam_id', 'steam_456')->first();

        $this->assertDatabaseHas('match_players', [
            'match_id' => $match->id,
            'player_id' => $player1->id,
            'team' => 'A',
        ]);

        $this->assertDatabaseHas('match_players', [
            'match_id' => $match->id,
            'player_id' => $player2->id,
            'team' => 'B',
        ]);
    }

    public function test_completion_callback_without_match_data(): void
    {
        $jobId = 'test-job-456';

        // Create a demo processing job first
        DemoProcessingJob::create([
            'uuid' => $jobId,
            'processing_status' => ProcessingStatus::PROCESSING,
            'progress_percentage' => 50,
            'current_step' => 'Processing in progress',
        ]);

        $payload = [
            'job_id' => $jobId,
            'status' => 'Completed',
            'progress' => 100,
            'current_step' => 'Processing complete',
        ];

        $response = $this->withHeaders([
            'X-API-Key' => 'test-api-key',
            'Content-Type' => 'application/json',
        ])->postJson('/api/job/callback/completion', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Completion update received',
                'job_id' => $jobId,
            ]);

        $this->assertDatabaseHas('demo_processing_jobs', [
            'uuid' => $jobId,
            'processing_status' => ProcessingStatus::COMPLETED,
            'progress_percentage' => 100,
            'current_step' => 'Processing complete',
        ]);
    }

    // Enhanced Progress Tracking Tests

    public function test_progress_callback_with_enhanced_progress_fields()
    {
        $jobId = 'test-job-enhanced-123';

        // Create a demo processing job
        DemoProcessingJob::factory()->create([
            'uuid' => $jobId,
            'processing_status' => ProcessingStatus::PENDING,
            'progress_percentage' => 0,
        ]);

        $payload = [
            'job_id' => $jobId,
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

        $response = $this->postJson('/api/job/callback/progress', $payload, [
            'X-API-Key' => 'test-api-key',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Progress update received',
            'job_id' => $jobId,
        ]);

        $this->assertDatabaseHas('demo_processing_jobs', [
            'uuid' => $jobId,
            'processing_status' => ProcessingStatus::PARSING,
            'progress_percentage' => 25,
            'current_step' => 'Processing grenade events',
            'step_progress' => 75,
            'total_steps' => 20,
            'current_step_num' => 6,
            'error_code' => null,
            'is_final' => false,
        ]);
    }

    public function test_progress_callback_with_error_code()
    {
        $jobId = 'test-job-error-123';

        DemoProcessingJob::factory()->create([
            'uuid' => $jobId,
            'processing_status' => ProcessingStatus::PARSING,
            'progress_percentage' => 30,
        ]);

        $payload = [
            'job_id' => $jobId,
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

        $response = $this->postJson('/api/job/callback/progress', $payload, [
            'X-API-Key' => 'test-api-key',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('demo_processing_jobs', [
            'uuid' => $jobId,
            'processing_status' => ProcessingStatus::FAILED,
            'error_message' => 'Demo file corrupted',
            'error_code' => 'DEMO_CORRUPTED',
            'is_final' => true,
        ]);
    }

    public function test_progress_callback_with_complex_context()
    {
        $jobId = 'test-job-context-123';

        DemoProcessingJob::factory()->create([
            'uuid' => $jobId,
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

        $payload = [
            'job_id' => $jobId,
            'status' => ProcessingStatus::PROCESSING_EVENTS->value,
            'progress' => 40,
            'current_step' => 'Processing round 5 of 16',
            'step_progress' => 50,
            'total_steps' => 34,
            'current_step_num' => 3,
            'context' => $complexContext,
        ];

        $response = $this->postJson('/api/job/callback/progress', $payload, [
            'X-API-Key' => 'test-api-key',
        ]);

        $response->assertStatus(200);

        $job = DemoProcessingJob::where('uuid', $jobId)->first();
        $this->assertEquals($complexContext, $job->context);
        $this->assertEquals(34, $job->total_steps);
        $this->assertEquals(50, $job->step_progress);
    }

    public function test_progress_callback_validation_with_invalid_enhanced_fields()
    {
        $jobId = 'test-job-invalid-123';

        DemoProcessingJob::factory()->create([
            'uuid' => $jobId,
            'processing_status' => ProcessingStatus::PENDING,
        ]);

        $payload = [
            'job_id' => $jobId,
            'status' => ProcessingStatus::PARSING->value,
            'progress' => 25,
            'current_step' => 'Processing',
            'step_progress' => 150, // Invalid: should be 0-100
            'total_steps' => 0, // Invalid: should be min 1
            'current_step_num' => -1, // Invalid: should be min 1
            'start_time' => 'invalid-date', // Invalid date format
            'is_final' => 'not-boolean', // Invalid: should be boolean
        ];

        $response = $this->postJson('/api/job/callback/progress', $payload, [
            'X-API-Key' => 'test-api-key',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'step_progress',
            'total_steps',
            'current_step_num',
            'start_time',
            'is_final',
        ]);
    }

    public function test_progress_callback_with_partial_enhanced_fields()
    {
        $jobId = 'test-job-partial-123';

        DemoProcessingJob::factory()->create([
            'uuid' => $jobId,
            'processing_status' => ProcessingStatus::PENDING,
            'progress_percentage' => 0,
        ]);

        // Test with only some enhanced fields (should be valid)
        $payload = [
            'job_id' => $jobId,
            'status' => ProcessingStatus::PROCESSING_EVENTS->value,
            'progress' => 50,
            'current_step' => 'Processing round events',
            'step_progress' => 30,
            'total_steps' => 18,
            'current_step_num' => 3,
            // Missing optional fields: start_time, last_update_time, error_code, context, is_final
        ];

        $response = $this->postJson('/api/job/callback/progress', $payload, [
            'X-API-Key' => 'test-api-key',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('demo_processing_jobs', [
            'uuid' => $jobId,
            'processing_status' => ProcessingStatus::PROCESSING_EVENTS,
            'progress_percentage' => 50,
            'current_step' => 'Processing round events',
            'step_progress' => 30,
            'total_steps' => 18,
            'current_step_num' => 3,
        ]);
    }

    public function test_progress_callback_backward_compatibility()
    {
        $jobId = 'test-job-backward-123';

        DemoProcessingJob::factory()->create([
            'uuid' => $jobId,
            'processing_status' => ProcessingStatus::PENDING,
            'progress_percentage' => 0,
        ]);

        // Test with only basic fields (backward compatibility)
        $payload = [
            'job_id' => $jobId,
            'status' => ProcessingStatus::PARSING->value,
            'progress' => 20,
            'current_step' => 'Parsing demo file',
            // No enhanced fields provided
        ];

        $response = $this->postJson('/api/job/callback/progress', $payload, [
            'X-API-Key' => 'test-api-key',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('demo_processing_jobs', [
            'uuid' => $jobId,
            'processing_status' => ProcessingStatus::PARSING,
            'progress_percentage' => 20,
            'current_step' => 'Parsing demo file',
        ]);
    }

    public function test_handle_event_returns_404_for_nonexistent_job()
    {
        $jobId = 'nonexistent-job-123';
        $eventName = 'gunfight';

        $payload = [
            'data' => [],
        ];

        $response = $this->withHeaders([
            'X-API-Key' => 'test-api-key',
            'Content-Type' => 'application/json',
        ])->postJson("/api/job/{$jobId}/event/{$eventName}", $payload);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Job not found for match creation',
                'job_id' => $jobId,
                'event_name' => $eventName,
            ]);
    }

    public function test_handle_event_returns_404_for_nonexistent_match()
    {
        $job = DemoProcessingJob::factory()->create();
        $jobId = $job->uuid;
        $eventName = 'gunfight';

        $payload = [
            'data' => [],
        ];

        $response = $this->withHeaders([
            'X-API-Key' => 'test-api-key',
            'Content-Type' => 'application/json',
        ])->postJson("/api/job/{$jobId}/event/{$eventName}", $payload);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Match not found for match creation',
                'job_id' => $jobId,
                'event_name' => $eventName,
            ]);
    }

    public function test_progress_callback_returns_404_for_nonexistent_job()
    {
        $jobId = 'nonexistent-job-456';

        $payload = [
            'job_id' => $jobId,
            'status' => 'Processing',
            'progress' => 50,
            'current_step' => 'Processing demo',
        ];

        $response = $this->withHeaders([
            'X-API-Key' => 'test-api-key',
            'Content-Type' => 'application/json',
        ])->postJson('/api/job/callback/progress', $payload);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Job not found for match creation',
                'job_id' => $jobId,
            ]);
    }

    public function test_completion_callback_returns_404_for_nonexistent_job()
    {
        $jobId = 'nonexistent-job-789';

        $payload = [
            'job_id' => $jobId,
            'status' => 'Completed',
            'progress' => 100,
            'current_step' => 'Processing complete',
        ];

        $response = $this->withHeaders([
            'X-API-Key' => 'test-api-key',
            'Content-Type' => 'application/json',
        ])->postJson('/api/job/callback/completion', $payload);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Job not found for match creation',
                'job_id' => $jobId,
            ]);
    }

    public function test_progress_callback_with_missing_required_fields()
    {
        $job = DemoProcessingJob::factory()->create();
        $jobId = $job->uuid;

        $payload = [
            'job_id' => $jobId,
            // Missing required fields: status, progress, current_step
        ];

        $response = $this->withHeaders([
            'X-API-Key' => 'test-api-key',
            'Content-Type' => 'application/json',
        ])->postJson('/api/job/callback/progress', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status', 'progress', 'current_step']);
    }

    public function test_completion_callback_with_missing_required_fields()
    {
        $job = DemoProcessingJob::factory()->create();
        $jobId = $job->uuid;

        $payload = [
            'job_id' => $jobId,
            // Missing required fields: status, progress, current_step
        ];

        $response = $this->withHeaders([
            'X-API-Key' => 'test-api-key',
            'Content-Type' => 'application/json',
        ])->postJson('/api/job/callback/completion', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }
}
