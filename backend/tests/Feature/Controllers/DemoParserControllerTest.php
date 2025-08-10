<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\DemoProcessingJob;
use App\Models\GameMatch;
use App\Models\Player;
use App\Enums\ProcessingStatus;

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
        $jobId = 'test-job-123';
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
                    'player_1_position' => ['x' => 100.5, 'y' => 200.3, 'z' => 50.0],
                    'player_2_position' => ['x' => 150.2, 'y' => 180.7, 'z' => 50.0],
                    'distance' => 50.0,
                    'headshot' => true,
                    'wallbang' => false,
                    'penetrated_objects' => 0,
                    'victor_steam_id' => 'steam_123',
                    'damage_dealt' => 100,
                ]
            ]
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
        $jobId = 'test-job-456';
        $eventName = 'round';

        $payload = [
            'data' => [
                [
                    'round_number' => 1,
                    'tick_timestamp' => 12345,
                    'event_type' => 'start',
                ]
            ]
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

    public function test_handle_event_with_invalid_event_name()
    {
        $jobId = 'test-job-789';
        $eventName = 'invalid_event';

        $payload = [
            'data' => []
        ];

        $response = $this->withHeaders([
            'X-API-Key' => 'test-api-key',
            'Content-Type' => 'application/json',
        ])->postJson("/api/job/{$jobId}/event/{$eventName}", $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['data']);
    }

    public function test_handle_event_without_api_key()
    {
        $jobId = 'test-job-999';
        $eventName = 'gunfight';

        $payload = [
            'data' => []
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
            'data' => []
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
                'start_timestamp' => '2025-08-10T01:00:00Z',
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
}
