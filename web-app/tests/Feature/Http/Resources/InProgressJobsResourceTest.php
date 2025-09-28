<?php

namespace Tests\Feature\Http\Resources;

use App\Http\Resources\InProgressJobsResource;
use App\Models\DemoProcessingJob;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InProgressJobsResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_to_array_returns_basic_job_data()
    {
        $user = User::factory()->create();
        $match = GameMatch::factory()->create();
        $job = DemoProcessingJob::factory()->create([
            'match_id' => $match->id,
            'user_id' => $user->id,
            'processing_status' => 'Processing',
            'progress_percentage' => 50,
            'current_step' => 'parsing',
            'error_message' => null,
            'step_progress' => 25,
            'total_steps' => 4,
            'current_step_num' => 2,
            'start_time' => now(),
            'last_update_time' => now(),
            'error_code' => null,
            'context' => ['key' => 'value'],
            'is_final' => false,
        ]);

        $resource = new InProgressJobsResource($job);
        $data = $resource->toArray(request());

        $this->assertEquals('Processing', $data['processing_status']->value);
        $this->assertEquals(50, $data['progress_percentage']);
        $this->assertEquals('parsing', $data['current_step']);
        $this->assertNull($data['error_message']);
        $this->assertEquals(25, $data['step_progress']);
        $this->assertEquals(4, $data['total_steps']);
        $this->assertEquals(2, $data['current_step_num']);
        $this->assertNotNull($data['start_time']);
        $this->assertNotNull($data['last_update_time']);
        $this->assertNull($data['error_code']);
        $this->assertEquals(['key' => 'value'], $data['context']);
        $this->assertFalse($data['is_final']);
    }

    public function test_to_array_includes_match_data_when_match_exists()
    {
        $user = User::factory()->create(['steam_id' => '76561198000000001']);
        $player = Player::factory()->create(['steam_id' => '76561198000000001']);

        $match = GameMatch::factory()->create([
            'map' => 'de_dust2',
            'winning_team_score' => 16,
            'losing_team_score' => 14,
            'winning_team' => 'CT',
            'match_type' => 'mm',
        ]);

        // Add player to match
        $match->players()->attach($player->id, ['team' => 'CT']);

        $job = DemoProcessingJob::factory()->create([
            'match_id' => $match->id,
            'user_id' => $user->id,
        ]);

        $resource = new InProgressJobsResource($job);
        $data = $resource->toArray(request());

        $this->assertEquals($match->id, $data['match_id']);
        $this->assertEquals('de_dust2', $data['map']);
        $this->assertEquals(16, $data['winning_team_score']);
        $this->assertEquals(14, $data['losing_team_score']);
        $this->assertEquals('CT', $data['winning_team']);
        $this->assertTrue($data['player_won_match']);
        $this->assertTrue($data['player_was_participant']);
        $this->assertEquals('CT', $data['player_team']);
        $this->assertEquals('mm', $data['match_type']->value);
        $this->assertNotNull($data['created_at']);
    }

    public function test_to_array_handles_player_did_not_win_match()
    {
        $user = User::factory()->create(['steam_id' => '76561198000000001']);
        $player = Player::factory()->create(['steam_id' => '76561198000000001']);

        $match = GameMatch::factory()->create([
            'winning_team' => 'T',
        ]);

        // Add player to match on CT team (losing team)
        $match->players()->attach($player->id, ['team' => 'CT']);

        $job = DemoProcessingJob::factory()->create([
            'match_id' => $match->id,
            'user_id' => $user->id,
        ]);

        $resource = new InProgressJobsResource($job);
        $data = $resource->toArray(request());

        $this->assertFalse($data['player_won_match']);
        $this->assertTrue($data['player_was_participant']);
        $this->assertEquals('CT', $data['player_team']);
    }

    public function test_to_array_handles_player_was_not_participant()
    {
        $user = User::factory()->create(['steam_id' => '76561198000000001']);
        $player = Player::factory()->create(['steam_id' => '76561198000000001']);

        $match = GameMatch::factory()->create();

        // Don't add player to match
        $job = DemoProcessingJob::factory()->create([
            'match_id' => $match->id,
            'user_id' => $user->id,
        ]);

        $resource = new InProgressJobsResource($job);
        $data = $resource->toArray(request());

        $this->assertFalse($data['player_won_match']);
        $this->assertFalse($data['player_was_participant']);
        $this->assertNull($data['player_team']);
    }

    public function test_to_array_handles_user_without_player()
    {
        $user = User::factory()->create();
        // Don't create player for user

        $match = GameMatch::factory()->create();

        $job = DemoProcessingJob::factory()->create([
            'match_id' => $match->id,
            'user_id' => $user->id,
        ]);

        $resource = new InProgressJobsResource($job);
        $data = $resource->toArray(request());

        $this->assertFalse($data['player_won_match']);
        $this->assertFalse($data['player_was_participant']);
        $this->assertNull($data['player_team']);
    }

    public function test_to_array_handles_job_without_match()
    {
        $user = User::factory()->create();

        $job = DemoProcessingJob::factory()->create([
            'match_id' => null,
            'user_id' => $user->id,
        ]);

        $resource = new InProgressJobsResource($job);
        $data = $resource->toArray(request());

        // Should only include basic job data, no match data
        $this->assertArrayNotHasKey('match_id', $data);
        $this->assertArrayNotHasKey('map', $data);
        $this->assertArrayNotHasKey('winning_team_score', $data);
        $this->assertArrayNotHasKey('losing_team_score', $data);
        $this->assertArrayNotHasKey('winning_team', $data);
        $this->assertArrayNotHasKey('player_won_match', $data);
        $this->assertArrayNotHasKey('player_was_participant', $data);
        $this->assertArrayNotHasKey('player_team', $data);
        $this->assertArrayNotHasKey('match_type', $data);
        $this->assertArrayNotHasKey('created_at', $data);
    }

    public function test_to_array_handles_job_with_error_data()
    {
        $user = User::factory()->create();
        $match = GameMatch::factory()->create();

        $job = DemoProcessingJob::factory()->create([
            'match_id' => $match->id,
            'user_id' => $user->id,
            'processing_status' => 'Failed',
            'error_message' => 'Parsing failed',
            'error_code' => 'PARSE_ERROR',
        ]);

        $resource = new InProgressJobsResource($job);
        $data = $resource->toArray(request());

        $this->assertEquals('Failed', $data['processing_status']->value);
        $this->assertEquals('Parsing failed', $data['error_message']);
        $this->assertEquals('PARSE_ERROR', $data['error_code']);
    }

    public function test_to_array_handles_final_job()
    {
        $user = User::factory()->create();
        $match = GameMatch::factory()->create();

        $job = DemoProcessingJob::factory()->create([
            'match_id' => $match->id,
            'user_id' => $user->id,
            'is_final' => true,
        ]);

        $resource = new InProgressJobsResource($job);
        $data = $resource->toArray(request());

        $this->assertTrue($data['is_final']);
    }
}
