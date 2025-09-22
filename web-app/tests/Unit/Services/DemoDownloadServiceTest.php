<?php

namespace Tests\Unit\Services;

use App\Services\DemoDownloadService;
use App\Services\RateLimiterService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DemoDownloadServiceTest extends TestCase
{
    private DemoDownloadService $demoDownloadService;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the RateLimiterService to avoid Redis dependency
        $this->mock(RateLimiterService::class, function ($mock) {
            $mock->shouldReceive('checkValveDemoUrlLimit')->andReturn(true);
            $mock->shouldReceive('waitForRateLimit')->andReturn();
        });

        $this->demoDownloadService = new DemoDownloadService;
    }

    public function test_download_demo_returns_file_path_on_successful_download(): void
    {
        $sharecode = 'CSGO-ABCDE-FGHIJ-KLMNO-PQRST-UVWXY';
        $demoContent = 'fake demo content';
        $demoUrl = 'https://replay123.valve.net/demo.dem';

        // Mock the valve demo URL service response
        Http::fake([
            'valve-demo-url-service.test/*' => Http::response([
                'demoUrl' => $demoUrl,
            ], 200),
            'replay*.valve.net/*' => Http::response($demoContent, 200),
        ]);

        // Set the configuration for the valve demo URL service
        config([
            'services.valve_demo_url_service.base_url' => 'https://valve-demo-url-service.test',
            'services.valve_demo_url_service.api_key' => 'test-api-key',
        ]);

        $result = $this->demoDownloadService->downloadDemo($sharecode);

        $this->assertNotNull($result);
        $this->assertFileExists($result);
        $this->assertEquals($demoContent, file_get_contents($result));

        $this->demoDownloadService->cleanupTempFile($result);
    }

    public function test_download_demo_returns_null_for_invalid_sharecode(): void
    {
        $invalidSharecode = 'invalid-sharecode';

        $result = $this->demoDownloadService->downloadDemo($invalidSharecode);

        $this->assertNull($result);
    }

    public function test_download_demo_returns_null_on_download_failure(): void
    {
        $sharecode = 'CSGO-ABCDE-FGHIJ-KLMNO-PQRST-UVWXY';

        Http::fake([
            'replay*.valve.net/*' => Http::response([], 404),
        ]);

        $result = $this->demoDownloadService->downloadDemo($sharecode);

        $this->assertNull($result);
    }

    public function test_cleanup_temp_file_removes_file(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_demo_');
        file_put_contents($tempFile, 'test content');

        $this->assertFileExists($tempFile);

        $this->demoDownloadService->cleanupTempFile($tempFile);

        $this->assertFileDoesNotExist($tempFile);
    }

    public function test_cleanup_temp_file_handles_nonexistent_file(): void
    {
        $nonexistentFile = '/tmp/nonexistent_file.dem';

        $this->assertFileDoesNotExist($nonexistentFile);

        $this->demoDownloadService->cleanupTempFile($nonexistentFile);

        $this->assertFileDoesNotExist($nonexistentFile);
    }
}
