<?php

namespace Tests\Unit\Services;

use App\Exceptions\ParserServiceConnectorException;
use App\Services\ParserServiceConnector;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ParserServiceConnectorTest extends TestCase
{
    private ParserServiceConnector $connector;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up test configuration
        config([
            'services.parser.base_url' => 'http://localhost:8080',
            'services.parser.api_key' => 'test-api-key',
            'app.url' => 'http://localhost:8000',
        ]);

        $this->connector = new ParserServiceConnector;
    }

    public function test_check_service_health_succeeds_when_service_is_healthy()
    {
        Http::fake([
            'http://localhost:8080/health' => Http::response(['status' => 'healthy'], 200),
        ]);

        // Should not throw an exception
        $this->connector->checkServiceHealth();

        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    public function test_check_service_health_throws_exception_when_service_is_unavailable()
    {
        Http::fake([
            'http://localhost:8080/health' => Http::response(['error' => 'Service unavailable'], 503),
        ]);

        $this->expectException(ParserServiceConnectorException::class);
        $this->expectExceptionMessage('Parser service is unavailable');

        $this->connector->checkServiceHealth();
    }

    public function test_check_service_health_throws_exception_on_network_error()
    {
        Http::fake(function () {
            throw new \Exception('Network error');
        });

        $this->expectException(ParserServiceConnectorException::class);
        $this->expectExceptionMessage('Parser service is unavailable');

        $this->connector->checkServiceHealth();
    }

    public function test_upload_demo_succeeds_with_valid_file()
    {
        Http::fake([
            'http://localhost:8080/health' => Http::response(['status' => 'healthy'], 200),
            'http://localhost:8080/api/parse-demo' => Http::response([
                'job_id' => 'test-job-123',
                'status' => 'processing',
            ], 200),
        ]);

        // Create a temporary file for testing
        $tempFile = tempnam(sys_get_temp_dir(), 'test_demo');
        file_put_contents($tempFile, 'fake demo content');

        try {
            $result = $this->connector->uploadDemo($tempFile, 'test-job-123');

            $this->assertIsArray($result);
            $this->assertEquals('test-job-123', $result['job_id']);
            $this->assertEquals('processing', $result['status']);

            // Verify the request was made with correct parameters
            Http::assertSent(function (Request $request) {
                return $request->url() === 'http://localhost:8080/api/parse-demo' &&
                    $request->hasHeader('X-API-Key', 'test-api-key') &&
                    $request->hasHeader('Accept', 'application/json');
            });
        } finally {
            unlink($tempFile);
        }
    }

    public function test_upload_demo_throws_exception_when_service_unhealthy()
    {
        Http::fake([
            'http://localhost:8080/health' => Http::response(['error' => 'Service unavailable'], 503),
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'test_demo');
        file_put_contents($tempFile, 'fake demo content');

        try {
            $this->expectException(ParserServiceConnectorException::class);
            $this->expectExceptionMessage('Parser service is unavailable');

            $this->connector->uploadDemo($tempFile, 'test-job-123');
        } finally {
            unlink($tempFile);
        }
    }

    public function test_upload_demo_throws_exception_on_upload_failure()
    {
        Http::fake([
            'http://localhost:8080/health' => Http::response(['status' => 'healthy'], 200),
            'http://localhost:8080/api/parse-demo' => Http::response(['error' => 'Upload failed'], 400),
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'test_demo');
        file_put_contents($tempFile, 'fake demo content');

        try {
            $this->expectException(ParserServiceConnectorException::class);

            $this->connector->uploadDemo($tempFile, 'test-job-123');
        } finally {
            unlink($tempFile);
        }
    }

    public function test_upload_demo_throws_exception_on_network_error()
    {
        Http::fake([
            'http://localhost:8080/health' => Http::response(['status' => 'healthy'], 200),
        ]);

        Http::fake(function () {
            throw new \Exception('Network error');
        });

        $tempFile = tempnam(sys_get_temp_dir(), 'test_demo');
        file_put_contents($tempFile, 'fake demo content');

        try {
            $this->expectException(ParserServiceConnectorException::class);

            $this->connector->uploadDemo($tempFile, 'test-job-123');
        } finally {
            unlink($tempFile);
        }
    }

    public function test_upload_demo_handles_file_not_found()
    {
        Http::fake([
            'http://localhost:8080/health' => Http::response(['status' => 'healthy'], 200),
        ]);

        $this->expectException(ParserServiceConnectorException::class);

        $this->connector->uploadDemo('/nonexistent/file.dem', 'test-job-123');
    }

    public function test_constructor_sets_correct_urls()
    {
        $connector = new ParserServiceConnector;

        // Use reflection to access private properties
        $reflection = new \ReflectionClass($connector);

        $progressCallbackURL = $reflection->getProperty('progressCallbackURL');
        $progressCallbackURL->setAccessible(true);

        $completionCallbackURL = $reflection->getProperty('completionCallbackURL');
        $completionCallbackURL->setAccessible(true);

        $parseDemoURL = $reflection->getProperty('parseDemoURL');
        $parseDemoURL->setAccessible(true);

        $this->assertEquals('http://localhost:8000/api/job/callback/progress', $progressCallbackURL->getValue($connector));
        $this->assertEquals('http://localhost:8000/api/job/callback/completion', $completionCallbackURL->getValue($connector));
        $this->assertEquals('http://localhost:8080/api/parse-demo', $parseDemoURL->getValue($connector));
    }

    protected function tearDown(): void
    {
        Http::clearResolvedInstances();
        parent::tearDown();
    }
}
