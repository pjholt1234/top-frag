<?php

namespace Tests\Unit\Services;

use App\Services\SteamAPIConnector;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SteamApiServiceTest extends TestCase
{
    private SteamAPIConnector $steamApiService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->steamApiService = new SteamAPIConnector;
    }

    public function test_check_service_health_returns_true_when_api_is_healthy(): void
    {
        Http::fake([
            'api.steampowered.com/*' => Http::response(['response' => ['result' => 1]], 200),
        ]);

        $result = $this->steamApiService->checkServiceHealth();

        $this->assertTrue($result);
    }

    public function test_check_service_health_returns_false_when_api_is_unhealthy(): void
    {
        Http::fake([
            'api.steampowered.com/*' => Http::response([], 500),
        ]);

        $result = $this->steamApiService->checkServiceHealth();

        $this->assertFalse($result);
    }

    public function test_get_next_match_sharing_code_returns_sharecode_when_available(): void
    {
        config(['services.steam.api_key' => 'test-api-key']);

        // Create a new instance to ensure it picks up the config
        $steamApiService = new SteamAPIConnector;

        Http::fake([
            '*' => Http::response([
                'result' => [
                    'nextcode' => 'CSGO-ABCDE-FGHIJ-KLMNO-PQRST-UVWXY',
                ],
            ], 200),
        ]);

        $result = $steamApiService->getNextMatchSharingCode('76561198012345678', 'AAAA-AAAAA-AAAA', 'CSGO-12345-67890-ABCDE-FGHIJ-KLMNO');

        $this->assertEquals('CSGO-ABCDE-FGHIJ-KLMNO-PQRST-UVWXY', $result);
    }

    public function test_get_next_match_sharing_code_returns_null_when_no_next_code(): void
    {
        config(['services.steam.api_key' => 'test-api-key']);

        // Create a new instance to ensure it picks up the config
        $steamApiService = new SteamAPIConnector;

        Http::fake([
            '*' => Http::response([
                'result' => [],
            ], 200),
        ]);

        $result = $steamApiService->getNextMatchSharingCode('76561198012345678', 'AAAA-AAAAA-AAAA', 'CSGO-12345-67890-ABCDE-FGHIJ-KLMNO');

        $this->assertNull($result);
    }

    public function test_get_next_match_sharing_code_returns_null_when_api_key_missing(): void
    {
        config(['services.steam.api_key' => null]);

        $result = $this->steamApiService->getNextMatchSharingCode('76561198012345678', 'AAAA-AAAAA-AAAA', 'CSGO-12345-67890-ABCDE-FGHIJ-KLMNO');

        $this->assertNull($result);
    }

    public function test_get_next_match_sharing_code_returns_null_on_api_error(): void
    {
        config(['services.steam.api_key' => 'test-api-key']);

        // Create a new instance to ensure it picks up the config
        $steamApiService = new SteamAPIConnector;

        Http::fake([
            '*' => Http::response([], 500),
        ]);

        $result = $steamApiService->getNextMatchSharingCode('76561198012345678', 'AAAA-AAAAA-AAAA', 'CSGO-12345-67890-ABCDE-FGHIJ-KLMNO');

        $this->assertNull($result);
    }
}
