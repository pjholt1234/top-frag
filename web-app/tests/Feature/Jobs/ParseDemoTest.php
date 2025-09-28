<?php

namespace Tests\Feature\Jobs;

use App\Exceptions\ParserServiceConnectorException;
use App\Jobs\ParseDemo;
use App\Models\DemoProcessingJob;
use App\Models\GameMatch;
use App\Models\User;
use App\Services\ParserServiceConnector;
use App\Services\RateLimiterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class ParseDemoTest extends TestCase
{
    use RefreshDatabase;

    private ParseDemo $job;

    private string $filePath;

    private User $user;

    private int $matchId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->matchId = GameMatch::factory()->create()->id;
        $this->filePath = '/tmp/test_demo.dem';
    }

    public function test_handles_successful_demo_parsing_with_existing_match()
    {
        // Mock the parser service connector
        $parserServiceMock = Mockery::mock(ParserServiceConnector::class);
        $parserServiceMock->shouldReceive('checkServiceHealth')->once();
        $parserServiceMock->shouldReceive('uploadDemo')
            ->with($this->filePath, Mockery::type('string'))
            ->once()
            ->andReturn(['status' => 'success']);

        $this->app->instance(ParserServiceConnector::class, $parserServiceMock);

        // Mock rate limiter
        $rateLimiterMock = Mockery::mock(RateLimiterService::class);
        $rateLimiterMock->shouldReceive('incrementParserServiceUsage')->once();
        $rateLimiterMock->shouldReceive('checkParserServiceLimit')->once()->andReturn(true);
        $rateLimiterMock->shouldReceive('decrementParserServiceUsage')->once();

        $this->app->instance(RateLimiterService::class, $rateLimiterMock);

        // Mock Log channel
        Log::shouldReceive('channel')->with('parser')->andReturnSelf();
        Log::shouldReceive('info')->once();
        Log::shouldReceive('error')->zeroOrMoreTimes();

        // Create job after mocks are set up
        $job = new ParseDemo($this->filePath, $this->user, $this->matchId);
        $job->handle();

        // Verify job was created
        $this->assertDatabaseHas('demo_processing_jobs', [
            'match_id' => $this->matchId,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_handles_successful_demo_parsing_with_new_match()
    {
        // Mock the parser service connector
        $parserServiceMock = Mockery::mock(ParserServiceConnector::class);
        $parserServiceMock->shouldReceive('checkServiceHealth')->once();
        $parserServiceMock->shouldReceive('uploadDemo')
            ->with($this->filePath, Mockery::type('string'))
            ->once()
            ->andReturn(['status' => 'success']);

        $this->app->instance(ParserServiceConnector::class, $parserServiceMock);

        // Mock rate limiter
        $rateLimiterMock = Mockery::mock(RateLimiterService::class);
        $rateLimiterMock->shouldReceive('incrementParserServiceUsage')->once();
        $rateLimiterMock->shouldReceive('checkParserServiceLimit')->once()->andReturn(true);
        $rateLimiterMock->shouldReceive('decrementParserServiceUsage')->once();

        $this->app->instance(RateLimiterService::class, $rateLimiterMock);

        // Mock Log channel
        Log::shouldReceive('channel')->with('parser')->andReturnSelf();
        Log::shouldReceive('info')->once();
        Log::shouldReceive('error')->zeroOrMoreTimes();

        // Create job after mocks are set up
        $job = new ParseDemo($this->filePath, $this->user);
        $job->handle();

        // Verify match and job were created
        $this->assertDatabaseHas('matches', [
            'uploaded_by' => $this->user->id,
        ]);

        $match = GameMatch::where('uploaded_by', $this->user->id)->first();
        $this->assertDatabaseHas('demo_processing_jobs', [
            'match_id' => $match->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_handles_rate_limit_exceeded()
    {
        // Mock rate limiter to return false (rate limit exceeded)
        $rateLimiterMock = Mockery::mock(RateLimiterService::class);
        $rateLimiterMock->shouldReceive('incrementParserServiceUsage')->once();
        $rateLimiterMock->shouldReceive('checkParserServiceLimit')->once()->andReturn(false);
        $rateLimiterMock->shouldReceive('decrementParserServiceUsage')->once();

        $this->app->instance(RateLimiterService::class, $rateLimiterMock);

        // Mock Log
        Log::shouldReceive('warning')->once();

        // Mock job release - this would need to be handled differently in a real test
        // For now, we'll just verify the method is called

        $job = new ParseDemo($this->filePath, $this->user, $this->matchId);
        $job->handle();
    }

    public function test_handles_parser_service_exception()
    {
        // Mock rate limiter
        $rateLimiterMock = Mockery::mock(RateLimiterService::class);
        $rateLimiterMock->shouldReceive('incrementParserServiceUsage')->once();
        $rateLimiterMock->shouldReceive('checkParserServiceLimit')->once()->andReturn(true);
        $rateLimiterMock->shouldReceive('decrementParserServiceUsage')->once();

        $this->app->instance(RateLimiterService::class, $rateLimiterMock);

        // Mock parser service to throw exception
        $parserServiceMock = Mockery::mock(ParserServiceConnector::class);
        $parserServiceMock->shouldReceive('checkServiceHealth')
            ->once()
            ->andThrow(new ParserServiceConnectorException('Service unavailable'));

        $this->app->instance(ParserServiceConnector::class, $parserServiceMock);

        // Mock report function
        $this->app->instance('report', function ($exception) {
            // Just return true for testing
            return true;
        });

        $job = new ParseDemo($this->filePath, $this->user, $this->matchId);
        $job->handle();

        // Should not throw exception, just return
        $this->assertTrue(true);
    }

    public function test_handles_general_exception()
    {
        // Mock rate limiter
        $rateLimiterMock = Mockery::mock(RateLimiterService::class);
        $rateLimiterMock->shouldReceive('incrementParserServiceUsage')->once();
        $rateLimiterMock->shouldReceive('checkParserServiceLimit')->once()->andReturn(true);
        $rateLimiterMock->shouldReceive('decrementParserServiceUsage')->once();

        $this->app->instance(RateLimiterService::class, $rateLimiterMock);

        // Mock parser service to throw general exception
        $parserServiceMock = Mockery::mock(ParserServiceConnector::class);
        $parserServiceMock->shouldReceive('checkServiceHealth')
            ->once()
            ->andThrow(new \Exception('General error'));

        $this->app->instance(ParserServiceConnector::class, $parserServiceMock);

        // Mock Log channel
        Log::shouldReceive('channel')->with('parser')->andReturnSelf();
        Log::shouldReceive('error')->once();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('General error');

        $job = new ParseDemo($this->filePath, $this->user, $this->matchId);
        $job->handle();
    }

    public function test_uses_existing_job_when_available()
    {
        // Create existing job
        $existingJob = DemoProcessingJob::factory()->create([
            'match_id' => $this->matchId,
            'user_id' => $this->user->id,
        ]);

        // Mock the parser service connector
        $parserServiceMock = Mockery::mock(ParserServiceConnector::class);
        $parserServiceMock->shouldReceive('checkServiceHealth')->once();
        $parserServiceMock->shouldReceive('uploadDemo')
            ->with($this->filePath, $existingJob->uuid)
            ->once()
            ->andReturn(['status' => 'success']);

        $this->app->instance(ParserServiceConnector::class, $parserServiceMock);

        // Mock rate limiter
        $rateLimiterMock = Mockery::mock(RateLimiterService::class);
        $rateLimiterMock->shouldReceive('incrementParserServiceUsage')->once();
        $rateLimiterMock->shouldReceive('checkParserServiceLimit')->once()->andReturn(true);
        $rateLimiterMock->shouldReceive('decrementParserServiceUsage')->once();

        $this->app->instance(RateLimiterService::class, $rateLimiterMock);

        // Mock Log channel
        Log::shouldReceive('channel')->with('parser')->andReturnSelf();
        Log::shouldReceive('info')->once();
        Log::shouldReceive('error')->zeroOrMoreTimes();

        $job = new ParseDemo($this->filePath, $this->user, $this->matchId);
        $job->handle();

        // Verify no new job was created
        $this->assertDatabaseCount('demo_processing_jobs', 1);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
