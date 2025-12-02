<?php

namespace Tests\Unit\Services;

use App\Exceptions\FaceITAPIConnectorException;
use App\Services\FaceITAPIConnector;
use Exception;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FaceITAPIConnectorTest extends TestCase
{
    private FaceITAPIConnector $connector;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up test configuration
        config([
            'services.faceit.api_key' => 'test-api-key',
        ]);

        $this->connector = new FaceITAPIConnector;
    }

    public function test_get_returns_data_on_successful_request(): void
    {
        $expectedData = [
            'player_id' => '12345',
            'nickname' => 'TestPlayer',
            'avatar' => 'https://example.com/avatar.jpg',
        ];

        Http::fake([
            'open.faceit.com/data/v4/players*' => Http::response($expectedData, 200),
        ]);

        $result = $this->connector->get('players', ['nickname' => 'TestPlayer']);

        $this->assertIsArray($result);
        $this->assertEquals($expectedData, $result);

        // Verify the request was made with correct parameters
        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), 'open.faceit.com/data/v4/players') &&
                $request->hasHeader('Authorization', 'Bearer test-api-key') &&
                $request->hasHeader('Accept', 'application/json') &&
                $request->data()['nickname'] === 'TestPlayer';
        });
    }

    public function test_get_throws_exception_when_api_key_not_configured(): void
    {
        config(['services.faceit.api_key' => null]);

        $this->expectException(FaceITAPIConnectorException::class);
        $this->expectExceptionMessage('FACEIT API configuration error');

        new FaceITAPIConnector;
    }

    public function test_get_throws_bad_request_exception_on_400(): void
    {
        Http::fake([
            'open.faceit.com/data/v4/players*' => Http::response([
                'errors' => [
                    ['message' => 'Invalid parameter'],
                ],
            ], 400),
        ]);

        $this->expectException(FaceITAPIConnectorException::class);
        $this->expectExceptionMessage('FACEIT API bad request');

        $this->connector->get('players', ['nickname' => '']);
    }

    public function test_get_throws_authentication_exception_on_401(): void
    {
        Http::fake([
            'open.faceit.com/data/v4/players*' => Http::response([
                'message' => 'Invalid API key',
            ], 401),
        ]);

        $this->expectException(FaceITAPIConnectorException::class);
        $this->expectExceptionMessage('FACEIT API authentication failed');

        $this->connector->get('players', ['nickname' => 'TestPlayer']);
    }

    public function test_get_throws_authentication_exception_on_403(): void
    {
        Http::fake([
            'open.faceit.com/data/v4/players*' => Http::response([], 403),
        ]);

        $this->expectException(FaceITAPIConnectorException::class);
        $this->expectExceptionMessage('Access forbidden');

        $this->connector->get('players', ['nickname' => 'TestPlayer']);
    }

    public function test_get_throws_not_found_exception_on_404(): void
    {
        Http::fake([
            'open.faceit.com/data/v4/players*' => Http::response([
                'message' => 'Player not found',
            ], 404),
        ]);

        $this->expectException(FaceITAPIConnectorException::class);
        $this->expectExceptionMessage('FACEIT API resource not found');

        $this->connector->get('players', ['nickname' => 'NonExistentPlayer']);
    }

    public function test_get_throws_rate_limit_exception_on_429(): void
    {
        Http::fake([
            'open.faceit.com/data/v4/players*' => Http::response([
                'message' => 'Rate limit exceeded',
            ], 429),
        ]);

        $this->expectException(FaceITAPIConnectorException::class);
        $this->expectExceptionMessage('FACEIT API rate limit exceeded');

        $this->connector->get('players', ['nickname' => 'TestPlayer']);
    }

    public function test_get_throws_service_unavailable_exception_on_503(): void
    {
        Http::fake([
            'open.faceit.com/data/v4/players*' => Http::response([
                'message' => 'Service temporarily unavailable',
            ], 503),
        ]);

        $this->expectException(FaceITAPIConnectorException::class);
        $this->expectExceptionMessage('FACEIT API service is unavailable');

        $this->connector->get('players', ['nickname' => 'TestPlayer']);
    }

    public function test_get_throws_request_failed_exception_on_other_error_codes(): void
    {
        Http::fake([
            'open.faceit.com/data/v4/players*' => Http::response([], 500),
        ]);

        $this->expectException(FaceITAPIConnectorException::class);
        $this->expectExceptionMessage('FACEIT API request failed');

        $this->connector->get('players', ['nickname' => 'TestPlayer']);
    }

    public function test_get_throws_exception_on_network_error(): void
    {
        Http::fake(function () {
            throw new Exception('Network connection failed');
        });

        $this->expectException(FaceITAPIConnectorException::class);
        $this->expectExceptionMessage('Network error');

        $this->connector->get('players', ['nickname' => 'TestPlayer']);
    }

    public function test_get_handles_error_message_from_errors_array(): void
    {
        Http::fake([
            'open.faceit.com/data/v4/players*' => Http::response([
                'errors' => [
                    ['message' => 'Error 1'],
                    ['message' => 'Error 2'],
                ],
            ], 400),
        ]);

        try {
            $this->connector->get('players', ['nickname' => 'TestPlayer']);
            $this->fail('Expected FaceITAPIConnectorException was not thrown');
        } catch (FaceITAPIConnectorException $e) {
            $this->assertStringContainsString('Error 1', $e->getMessage());
            $this->assertStringContainsString('Error 2', $e->getMessage());
        }
    }

    public function test_get_handles_error_message_from_message_field(): void
    {
        Http::fake([
            'open.faceit.com/data/v4/players*' => Http::response([
                'message' => 'Custom error message',
            ], 400),
        ]);

        try {
            $this->connector->get('players', ['nickname' => 'TestPlayer']);
            $this->fail('Expected FaceITAPIConnectorException was not thrown');
        } catch (FaceITAPIConnectorException $e) {
            $this->assertStringContainsString('Custom error message', $e->getMessage());
        }
    }

    public function test_get_handles_non_array_response(): void
    {
        Http::fake([
            'open.faceit.com/data/v4/players*' => Http::response('invalid json', 200),
        ]);

        $result = $this->connector->get('players', ['nickname' => 'TestPlayer']);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_get_constructs_correct_url_with_endpoint(): void
    {
        Http::fake([
            'open.faceit.com/data/v4/test-endpoint*' => Http::response([], 200),
        ]);

        $this->connector->get('test-endpoint');

        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), 'open.faceit.com/data/v4/test-endpoint');
        });
    }

    public function test_get_handles_endpoint_with_leading_slash(): void
    {
        Http::fake([
            'open.faceit.com/data/v4/players*' => Http::response([], 200),
        ]);

        $this->connector->get('/players');

        Http::assertSent(function (Request $request) {
            $url = $request->url();

            // Verify the URL is correctly constructed with the endpoint
            return str_contains($url, 'open.faceit.com/data/v4/players') &&
                str_ends_with(parse_url($url, PHP_URL_PATH), '/players');
        });
    }

    protected function tearDown(): void
    {
        Http::clearResolvedInstances();
        parent::tearDown();
    }
}
