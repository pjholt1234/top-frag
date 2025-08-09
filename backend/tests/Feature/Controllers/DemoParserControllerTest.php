<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

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
                    'distance' => 50.0,
                    'headshot' => true,
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
                    'event_type' => 'round_start',
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

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => 'Invalid event name. Valid events: round, gunfight, grenade, damage',
            ]);
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
}
