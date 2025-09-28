<?php

namespace Tests\Feature\Models;

use App\Enums\ProcessingStatus;
use App\Models\DemoProcessingJob;
use App\Models\GameMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoProcessingJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_create_a_demo_processing_job()
    {
        $user = User::factory()->create();
        $match = GameMatch::factory()->create();

        $job = DemoProcessingJob::create([
            'uuid' => 'test-uuid-123',
            'match_id' => $match->id,
            'user_id' => $user->id,
            'processing_status' => ProcessingStatus::PENDING,
            'progress_percentage' => 0,
            'error_message' => null,
            'started_at' => now(),
            'completed_at' => null,
            'current_step' => 'uploading',
            'step_progress' => 0,
            'total_steps' => 4,
            'current_step_num' => 1,
            'start_time' => now(),
            'last_update_time' => now(),
            'error_code' => null,
            'context' => ['key' => 'value'],
            'is_final' => false,
        ]);

        $this->assertDatabaseHas('demo_processing_jobs', [
            'id' => $job->id,
            'uuid' => 'test-uuid-123',
            'match_id' => $match->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_it_has_fillable_attributes()
    {
        $job = new DemoProcessingJob;
        $fillable = $job->getFillable();

        $expectedFillable = [
            'uuid',
            'match_id',
            'processing_status',
            'progress_percentage',
            'error_message',
            'started_at',
            'completed_at',
            'current_step',
            'user_id',
            'step_progress',
            'total_steps',
            'current_step_num',
            'start_time',
            'last_update_time',
            'error_code',
            'context',
            'is_final',
        ];

        $this->assertEquals($expectedFillable, $fillable);
    }

    public function test_it_casts_attributes_correctly()
    {
        $user = User::factory()->create();
        $match = GameMatch::factory()->create();

        $job = DemoProcessingJob::create([
            'uuid' => 'test-uuid-123',
            'match_id' => $match->id,
            'user_id' => $user->id,
            'processing_status' => ProcessingStatus::PROCESSING,
            'progress_percentage' => 50,
            'error_message' => null,
            'started_at' => now(),
            'completed_at' => null,
            'current_step' => 'parsing',
            'step_progress' => 25,
            'total_steps' => 4,
            'current_step_num' => 2,
            'start_time' => now(),
            'last_update_time' => now(),
            'error_code' => null,
            'context' => ['key' => 'value'],
            'is_final' => false,
        ]);

        $this->assertInstanceOf(\DateTime::class, $job->started_at);
        $this->assertNull($job->completed_at);
        $this->assertInstanceOf(ProcessingStatus::class, $job->processing_status);
        $this->assertIsInt($job->progress_percentage);
        $this->assertIsInt($job->match_id);
        $this->assertIsInt($job->step_progress);
        $this->assertIsInt($job->total_steps);
        $this->assertIsInt($job->current_step_num);
        $this->assertInstanceOf(\DateTime::class, $job->start_time);
        $this->assertInstanceOf(\DateTime::class, $job->last_update_time);
        $this->assertIsArray($job->context);
        $this->assertIsBool($job->is_final);
    }

    public function test_it_belongs_to_match()
    {
        $user = User::factory()->create();
        $match = GameMatch::factory()->create();

        $job = DemoProcessingJob::factory()->create([
            'match_id' => $match->id,
            'user_id' => $user->id,
        ]);

        $this->assertInstanceOf(GameMatch::class, $job->match);
        $this->assertEquals($match->id, $job->match->id);
    }

    public function test_it_belongs_to_user()
    {
        $user = User::factory()->create();
        $match = GameMatch::factory()->create();

        $job = DemoProcessingJob::factory()->create([
            'match_id' => $match->id,
            'user_id' => $user->id,
        ]);

        $this->assertInstanceOf(User::class, $job->user);
        $this->assertEquals($user->id, $job->user->id);
    }

    public function test_it_can_be_created_with_factory()
    {
        $job = DemoProcessingJob::factory()->create();

        $this->assertDatabaseHas('demo_processing_jobs', [
            'id' => $job->id,
        ]);
    }

    public function test_it_handles_processing_status_enum()
    {
        $user = User::factory()->create();
        $match = GameMatch::factory()->create();

        $job = DemoProcessingJob::create([
            'uuid' => 'test-uuid-123',
            'match_id' => $match->id,
            'user_id' => $user->id,
            'processing_status' => ProcessingStatus::COMPLETED,
            'progress_percentage' => 100,
            'error_message' => null,
            'started_at' => now(),
            'completed_at' => now(),
            'current_step' => 'completed',
            'step_progress' => 100,
            'total_steps' => 4,
            'current_step_num' => 4,
            'start_time' => now(),
            'last_update_time' => now(),
            'error_code' => null,
            'context' => ['key' => 'value'],
            'is_final' => true,
        ]);

        $this->assertEquals(ProcessingStatus::COMPLETED, $job->processing_status);
        $this->assertEquals(ProcessingStatus::COMPLETED->value, $job->processing_status->value);
    }

    public function test_it_handles_error_status()
    {
        $user = User::factory()->create();
        $match = GameMatch::factory()->create();

        $job = DemoProcessingJob::create([
            'uuid' => 'test-uuid-123',
            'match_id' => $match->id,
            'user_id' => $user->id,
            'processing_status' => ProcessingStatus::FAILED,
            'progress_percentage' => 25,
            'error_message' => 'Parsing failed',
            'started_at' => now(),
            'completed_at' => now(),
            'current_step' => 'parsing',
            'step_progress' => 0,
            'total_steps' => 4,
            'current_step_num' => 2,
            'start_time' => now(),
            'last_update_time' => now(),
            'error_code' => 'PARSE_ERROR',
            'context' => ['error' => 'details'],
            'is_final' => true,
        ]);

        $this->assertEquals(ProcessingStatus::FAILED, $job->processing_status);
        $this->assertEquals('Parsing failed', $job->error_message);
        $this->assertEquals('PARSE_ERROR', $job->error_code);
        $this->assertEquals(['error' => 'details'], $job->context);
    }
}
