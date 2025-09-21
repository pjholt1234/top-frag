<?php

namespace Tests\Feature\Controllers\Api;

use App\Jobs\ParseDemo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class UploadControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Queue::fake();
    }

    public function test_user_can_upload_demo_file_successfully()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create a fake .dem file with proper content
        $file = UploadedFile::fake()->createWithContent('test.dem', 'fake demo file content');

        $response = $this->postJson('/api/user/upload/demo', [
            'demo' => $file,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Demo process received',
            ]);

        // Assert file was stored - check that a file was stored in the demos directory
        $files = Storage::disk('public')->files('demos');
        $this->assertNotEmpty($files);

        // Assert job was dispatched
        Queue::assertPushed(ParseDemo::class, function ($job) use ($user) {
            // Use reflection to access private property
            $reflection = new \ReflectionClass($job);
            $userProperty = $reflection->getProperty('user');
            $userProperty->setAccessible(true);
            $jobUser = $userProperty->getValue($job);

            return $jobUser && $jobUser->id === $user->id;
        });
    }

    public function test_user_cannot_upload_invalid_file_type()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $file = UploadedFile::fake()->createWithContent('test.txt', 'fake text file content');

        $response = $this->postJson('/api/user/upload/demo', [
            'demo' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['demo']);
    }

    public function test_user_cannot_upload_file_larger_than_1_gb()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $file = UploadedFile::fake()->create('test.dem', 1073741825); // 1GB + 1 byte

        $response = $this->postJson('/api/user/upload/demo', [
            'demo' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['demo']);
    }

    public function test_user_can_upload_file_exactly_1_gb()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $file = UploadedFile::fake()->create('test.dem', 1073741824); // Exactly 1GB

        $response = $this->postJson('/api/user/upload/demo', [
            'demo' => $file,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Demo process received',
            ]);
    }

    public function test_unauthenticated_user_cannot_upload_demo()
    {
        $file = UploadedFile::fake()->createWithContent('test.dem', 'fake demo file content');

        $response = $this->postJson('/api/user/upload/demo', [
            'demo' => $file,
        ]);

        $response->assertStatus(401);
    }

    public function test_upload_handles_missing_file()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson('/api/user/upload/demo', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['demo']);
    }

    public function test_upload_handles_storage_exception()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Mock Storage to throw an exception
        $storageMock = Mockery::mock();
        $storageMock->shouldReceive('putFileAs')
            ->andThrow(new \Exception('Storage error'));

        Storage::shouldReceive('disk')
            ->with('public')
            ->andReturn($storageMock);

        $file = UploadedFile::fake()->createWithContent('test.dem', 'fake demo file content');

        Log::shouldReceive('channel')
            ->with('parser')
            ->andReturn(Mockery::mock([
                'error' => null,
            ]));

        $response = $this->postJson('/api/user/upload/demo', [
            'demo' => $file,
        ]);

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'error' => 'Internal server error',
                'message' => 'An unexpected error occurred while uploading the demo',
            ]);
    }

    public function test_upload_logs_error_when_exception_occurs()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $file = UploadedFile::fake()->createWithContent('test.dem', 'fake demo file content');

        // Mock Log to verify error logging
        $logMock = Mockery::mock();
        $logMock->shouldReceive('error')
            ->once()
            ->with('Unexpected error in demo upload via user API', Mockery::type('array'));

        Log::shouldReceive('channel')
            ->with('parser')
            ->andReturn($logMock);

        // Mock Storage to throw an exception
        $storageMock = Mockery::mock();
        $storageMock->shouldReceive('putFileAs')
            ->andThrow(new \Exception('Storage error'));

        Storage::shouldReceive('disk')
            ->with('public')
            ->andReturn($storageMock);

        $response = $this->postJson('/api/user/upload/demo', [
            'demo' => $file,
        ]);

        $response->assertStatus(500);
    }

    public function test_get_in_progress_jobs_returns_unauthorized_for_unauthenticated_user()
    {
        $response = $this->getJson('/api/user/upload/in-progress-jobs');

        $response->assertStatus(401);
    }

    public function test_get_in_progress_jobs_returns_empty_array_when_no_jobs()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson('/api/user/upload/in-progress-jobs');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'jobs' => [],
            ]);
    }

    public function test_get_in_progress_jobs_returns_only_incomplete_jobs()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create some demo processing jobs with different statuses
        $incompleteJob1 = \App\Models\DemoProcessingJob::factory()->create([
            'user_id' => $user->id,
            'progress_percentage' => 50,
            'processing_status' => \App\Enums\ProcessingStatus::PROCESSING,
        ]);

        $incompleteJob2 = \App\Models\DemoProcessingJob::factory()->create([
            'user_id' => $user->id,
            'progress_percentage' => 75,
            'processing_status' => \App\Enums\ProcessingStatus::PROCESSING,
        ]);

        $completedJob = \App\Models\DemoProcessingJob::factory()->create([
            'user_id' => $user->id,
            'progress_percentage' => 100,
            'processing_status' => \App\Enums\ProcessingStatus::COMPLETED,
        ]);

        $response = $this->getJson('/api/user/upload/in-progress-jobs');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'jobs' => [
                    '*' => [
                        'processing_status',
                        'progress_percentage',
                        'current_step',
                    ],
                ],
            ]);

        $jobs = $response->json('jobs');
        $this->assertCount(2, $jobs);

        // Verify that only incomplete jobs are returned by checking progress percentages
        $progressPercentages = collect($jobs)->pluck('progress_percentage')->toArray();
        $this->assertContains(50, $progressPercentages);
        $this->assertContains(75, $progressPercentages);
        $this->assertNotContains(100, $progressPercentages);
    }

    public function test_get_in_progress_jobs_returns_jobs_for_authenticated_user_only()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $this->actingAs($user1);

        // Create jobs for both users
        $user1Job = \App\Models\DemoProcessingJob::factory()->create([
            'user_id' => $user1->id,
            'progress_percentage' => 50,
            'processing_status' => \App\Enums\ProcessingStatus::PROCESSING,
        ]);

        $user2Job = \App\Models\DemoProcessingJob::factory()->create([
            'user_id' => $user2->id,
            'progress_percentage' => 30,
            'processing_status' => \App\Enums\ProcessingStatus::PROCESSING,
        ]);

        $response = $this->getJson('/api/user/upload/in-progress-jobs');

        $response->assertStatus(200);
        $jobs = $response->json('jobs');

        $this->assertCount(1, $jobs);
        $this->assertEquals(50, $jobs[0]['progress_percentage']);
    }

    public function test_get_in_progress_jobs_filters_by_progress_percentage()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create jobs with different progress percentages
        $job1 = \App\Models\DemoProcessingJob::factory()->create([
            'user_id' => $user->id,
            'progress_percentage' => 0,
            'processing_status' => \App\Enums\ProcessingStatus::PENDING,
        ]);

        $job2 = \App\Models\DemoProcessingJob::factory()->create([
            'user_id' => $user->id,
            'progress_percentage' => 50,
            'processing_status' => \App\Enums\ProcessingStatus::PROCESSING,
        ]);

        $job3 = \App\Models\DemoProcessingJob::factory()->create([
            'user_id' => $user->id,
            'progress_percentage' => 100,
            'processing_status' => \App\Enums\ProcessingStatus::PROCESSING,
        ]);

        $response = $this->getJson('/api/user/upload/in-progress-jobs');

        $response->assertStatus(200);
        $jobs = $response->json('jobs');

        $this->assertCount(2, $jobs);

        $progressPercentages = collect($jobs)->pluck('progress_percentage')->toArray();
        $this->assertContains(0, $progressPercentages);
        $this->assertContains(50, $progressPercentages);
        $this->assertNotContains(100, $progressPercentages);
    }

    public function test_get_in_progress_jobs_filters_by_processing_status()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create jobs with different processing statuses
        $pendingJob = \App\Models\DemoProcessingJob::factory()->create([
            'user_id' => $user->id,
            'progress_percentage' => 0,
            'processing_status' => \App\Enums\ProcessingStatus::PENDING,
        ]);

        $processingJob = \App\Models\DemoProcessingJob::factory()->create([
            'user_id' => $user->id,
            'progress_percentage' => 50,
            'processing_status' => \App\Enums\ProcessingStatus::PROCESSING,
        ]);

        $completedJob = \App\Models\DemoProcessingJob::factory()->create([
            'user_id' => $user->id,
            'progress_percentage' => 100,
            'processing_status' => \App\Enums\ProcessingStatus::COMPLETED,
        ]);

        $response = $this->getJson('/api/user/upload/in-progress-jobs');

        $response->assertStatus(200);
        $jobs = $response->json('jobs');

        $this->assertCount(2, $jobs);

        $processingStatuses = collect($jobs)->pluck('processing_status')->toArray();
        $this->assertContains('Pending', $processingStatuses);
        $this->assertContains('Processing', $processingStatuses);
        $this->assertNotContains('Completed', $processingStatuses);
    }
}
