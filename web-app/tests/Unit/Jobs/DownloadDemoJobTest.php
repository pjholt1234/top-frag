<?php

namespace Tests\Unit\Jobs;

use App\Jobs\DownloadDemoJob;
use App\Models\GameMatch;
use App\Services\DemoDownloadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DownloadDemoJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_handles_successful_demo_download(): void
    {
        Queue::fake();

        $sharecode = 'CSGO-test-sharecode';
        $userId = 1;
        $demoUrl = 'https://example.com/demo.dem';
        $demoFilePath = '/tmp/demo.dem';

        $demoDownloadMock = $this->mock(DemoDownloadService::class);
        $demoDownloadMock->shouldReceive('fetchDemoUrl')
            ->once()
            ->with($sharecode)
            ->andReturn($demoUrl);
        $demoDownloadMock->shouldReceive('downloadDemo')
            ->once()
            ->with($sharecode)
            ->andReturn($demoFilePath);

        $job = new DownloadDemoJob($sharecode, $userId);
        $job->handle();

        $this->assertDatabaseHas('matches', [
            'sharecode' => $sharecode,
            'demo_url' => $demoUrl,
            'uploaded_by' => null,
        ]);

        $this->assertDatabaseHas('demo_processing_jobs', [
            'match_id' => GameMatch::where('sharecode', $sharecode)->first()->id,
            'user_id' => null,
        ]);

        Queue::assertPushed(\App\Jobs\ParseDemo::class, 1);
    }

    public function test_handles_failed_demo_url_fetch(): void
    {
        Queue::fake();

        $sharecode = 'CSGO-test-sharecode';
        $userId = 1;

        $demoDownloadMock = $this->mock(DemoDownloadService::class);
        $demoDownloadMock->shouldReceive('fetchDemoUrl')
            ->once()
            ->with($sharecode)
            ->andReturn(null);

        $job = new DownloadDemoJob($sharecode, $userId);
        $job->handle();

        $this->assertDatabaseMissing('matches', [
            'sharecode' => $sharecode,
        ]);

        Queue::assertNotPushed(\App\Jobs\ParseDemo::class);
    }

    public function test_handles_failed_demo_download(): void
    {
        Queue::fake();

        $sharecode = 'CSGO-test-sharecode';
        $userId = 1;
        $demoUrl = 'https://example.com/demo.dem';

        $demoDownloadMock = $this->mock(DemoDownloadService::class);
        $demoDownloadMock->shouldReceive('fetchDemoUrl')
            ->once()
            ->with($sharecode)
            ->andReturn($demoUrl);
        $demoDownloadMock->shouldReceive('downloadDemo')
            ->once()
            ->with($sharecode)
            ->andReturn(null);

        $job = new DownloadDemoJob($sharecode, $userId);
        $job->handle();

        $this->assertDatabaseMissing('matches', [
            'sharecode' => $sharecode,
        ]);

        Queue::assertNotPushed(\App\Jobs\ParseDemo::class);
    }
}
