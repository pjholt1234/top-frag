<?php

namespace Tests\Feature\Services;

use App\Services\RateLimiterService;
use App\Services\SteamAPIConnector;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class SteamAPIConnectorTest extends TestCase
{
    use RefreshDatabase;

    private SteamAPIConnector $connector;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up config for Steam API key
        config(['services.steam.api_key' => 'test_api_key']);

        $this->connector = new SteamAPIConnector;
    }

    public function test_check_service_health_returns_true_when_api_is_healthy()
    {
        Http::fake([
            'https://api.steampowered.com/ISteamWebAPIUtil/GetServerInfo/v1/' => Http::response(['server_time' => 1234567890], 200),
        ]);

        $result = $this->connector->checkServiceHealth();

        $this->assertTrue($result);
    }

    public function test_check_service_health_returns_false_when_api_returns_error()
    {
        Http::fake([
            'https://api.steampowered.com/ISteamWebAPIUtil/GetServerInfo/v1/' => Http::response(['error' => 'Service unavailable'], 500),
        ]);

        Log::shouldReceive('warning')->once();

        $result = $this->connector->checkServiceHealth();

        $this->assertFalse($result);
    }

    public function test_check_service_health_returns_false_on_exception()
    {
        Http::fake([
            'https://api.steampowered.com/ISteamWebAPIUtil/GetServerInfo/v1/' => function () {
                throw new Exception('Network error');
            },
        ]);

        Log::shouldReceive('error')->once();

        $result = $this->connector->checkServiceHealth();

        $this->assertFalse($result);
    }

    public function test_get_next_match_sharing_code_returns_null_when_api_key_not_configured()
    {
        // Set config to null
        config(['services.steam.api_key' => null]);
        $connector = new SteamAPIConnector;

        Log::shouldReceive('error')->once();

        $result = $connector->getNextMatchSharingCode('76561198000000001', 'auth_code', 'known_code');

        $this->assertNull($result);
    }

    public function test_get_next_match_sharing_code_returns_sharecode_when_available()
    {
        Http::fake([
            'https://api.steampowered.com/ICSGOPlayers_730/GetNextMatchSharingCode/v1/*' => Http::response([
                'result' => [
                    'nextcode' => 'CSGO-ABC123-DEF456-GHI789-JKL012-MNO345',
                ],
            ], 200),
        ]);

        // Mock rate limiter
        $rateLimiterMock = Mockery::mock(RateLimiterService::class);
        $rateLimiterMock->shouldReceive('checkSteamApiLimit')->once()->andReturn(true);
        $rateLimiterMock->shouldReceive('waitForRateLimit')->zeroOrMoreTimes();
        $this->app->instance(RateLimiterService::class, $rateLimiterMock);

        Log::shouldReceive('info')->once();
        Log::shouldReceive('error')->zeroOrMoreTimes();

        $result = $this->connector->getNextMatchSharingCode('76561198000000001', 'auth_code', 'known_code');

        $this->assertEquals('CSGO-ABC123-DEF456-GHI789-JKL012-MNO345', $result);
    }

    public function test_get_next_match_sharing_code_returns_null_when_no_next_code()
    {
        Http::fake([
            'https://api.steampowered.com/ICSGOPlayers_730/GetNextMatchSharingCode/v1/*' => Http::response([
                'result' => [],
            ], 200),
        ]);

        // Mock rate limiter
        $rateLimiterMock = Mockery::mock(RateLimiterService::class);
        $rateLimiterMock->shouldReceive('checkSteamApiLimit')->once()->andReturn(true);
        $rateLimiterMock->shouldReceive('waitForRateLimit')->zeroOrMoreTimes();
        $this->app->instance(RateLimiterService::class, $rateLimiterMock);

        Log::shouldReceive('info')->once();
        Log::shouldReceive('error')->zeroOrMoreTimes();

        $result = $this->connector->getNextMatchSharingCode('76561198000000001', 'auth_code', 'known_code');

        $this->assertNull($result);
    }

    public function test_get_next_match_sharing_code_returns_null_on_api_error()
    {
        Http::fake([
            'https://api.steampowered.com/ICSGOPlayers_730/GetNextMatchSharingCode/v1/*' => Http::response(['error' => 'Invalid request'], 400),
        ]);

        // Mock rate limiter
        $rateLimiterMock = Mockery::mock(RateLimiterService::class);
        $rateLimiterMock->shouldReceive('checkSteamApiLimit')->once()->andReturn(true);
        $rateLimiterMock->shouldReceive('waitForRateLimit')->zeroOrMoreTimes();
        $this->app->instance(RateLimiterService::class, $rateLimiterMock);

        Log::shouldReceive('warning')->once();

        $result = $this->connector->getNextMatchSharingCode('76561198000000001', 'auth_code', 'known_code');

        $this->assertNull($result);
    }

    public function test_get_next_match_sharing_code_returns_null_on_exception()
    {
        Http::fake([
            'https://api.steampowered.com/ICSGOPlayers_730/GetNextMatchSharingCode/v1/*' => function () {
                throw new Exception('Network error');
            },
        ]);

        // Mock rate limiter
        $rateLimiterMock = Mockery::mock(RateLimiterService::class);
        $rateLimiterMock->shouldReceive('checkSteamApiLimit')->once()->andReturn(true);
        $rateLimiterMock->shouldReceive('waitForRateLimit')->zeroOrMoreTimes();
        $this->app->instance(RateLimiterService::class, $rateLimiterMock);

        Log::shouldReceive('error')->once();

        $result = $this->connector->getNextMatchSharingCode('76561198000000001', 'auth_code', 'known_code');

        $this->assertNull($result);
    }

    public function test_get_player_summaries_returns_null_when_api_key_not_configured()
    {
        // Set config to null
        config(['services.steam.api_key' => null]);
        $connector = new SteamAPIConnector;

        Log::shouldReceive('error')->once();

        $result = $connector->getPlayerSummaries(['76561198000000001']);

        $this->assertNull($result);
    }

    public function test_get_player_summaries_returns_empty_array_when_no_steam_ids()
    {
        $result = $this->connector->getPlayerSummaries([]);

        $this->assertEquals([], $result);
    }

    public function test_get_player_summaries_returns_player_data_when_successful()
    {
        Http::fake([
            'https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/*' => Http::response([
                'response' => [
                    'players' => [
                        [
                            'steamid' => '76561198000000001',
                            'personaname' => 'TestPlayer1',
                            'profileurl' => 'https://steamcommunity.com/id/testplayer1',
                            'avatar' => 'avatar1.jpg',
                            'avatarmedium' => 'avatar1_medium.jpg',
                            'avatarfull' => 'avatar1_full.jpg',
                            'personastate' => 1,
                            'communityvisibilitystate' => 3,
                        ],
                        [
                            'steamid' => '76561198000000002',
                            'personaname' => 'TestPlayer2',
                            'profileurl' => 'https://steamcommunity.com/id/testplayer2',
                            'avatar' => 'avatar2.jpg',
                            'avatarmedium' => 'avatar2_medium.jpg',
                            'avatarfull' => 'avatar2_full.jpg',
                            'personastate' => 0,
                            'communityvisibilitystate' => 1,
                        ],
                    ],
                ],
            ], 200),
        ]);

        // Mock rate limiter
        $rateLimiterMock = Mockery::mock(RateLimiterService::class);
        $rateLimiterMock->shouldReceive('checkSteamApiLimit')->once()->andReturn(true);
        $rateLimiterMock->shouldReceive('waitForRateLimit')->zeroOrMoreTimes();
        $this->app->instance(RateLimiterService::class, $rateLimiterMock);

        Log::shouldReceive('info')->once();
        Log::shouldReceive('error')->zeroOrMoreTimes();

        $result = $this->connector->getPlayerSummaries(['76561198000000001', '76561198000000002']);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertArrayHasKey('76561198000000001', $result);
        $this->assertArrayHasKey('76561198000000002', $result);

        $player1 = $result['76561198000000001'];
        $this->assertEquals('76561198000000001', $player1['steam_id']);
        $this->assertEquals('TestPlayer1', $player1['persona_name']);
        $this->assertEquals('https://steamcommunity.com/id/testplayer1', $player1['profile_url']);
        $this->assertEquals('avatar1.jpg', $player1['avatar']);
        $this->assertEquals('avatar1_medium.jpg', $player1['avatar_medium']);
        $this->assertEquals('avatar1_full.jpg', $player1['avatar_full']);
        $this->assertEquals(1, $player1['persona_state']);
        $this->assertEquals(3, $player1['community_visibility_state']);

        $player2 = $result['76561198000000002'];
        $this->assertEquals('76561198000000002', $player2['steam_id']);
        $this->assertEquals('TestPlayer2', $player2['persona_name']);
        $this->assertEquals('https://steamcommunity.com/id/testplayer2', $player2['profile_url']);
        $this->assertEquals('avatar2.jpg', $player2['avatar']);
        $this->assertEquals('avatar2_medium.jpg', $player2['avatar_medium']);
        $this->assertEquals('avatar2_full.jpg', $player2['avatar_full']);
        $this->assertEquals(0, $player2['persona_state']);
        $this->assertEquals(1, $player2['community_visibility_state']);
    }

    public function test_get_player_summaries_returns_empty_array_when_no_players_returned()
    {
        Http::fake([
            'https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/*' => Http::response([
                'response' => [],
            ], 200),
        ]);

        // Mock rate limiter
        $rateLimiterMock = Mockery::mock(RateLimiterService::class);
        $rateLimiterMock->shouldReceive('checkSteamApiLimit')->once()->andReturn(true);
        $rateLimiterMock->shouldReceive('waitForRateLimit')->zeroOrMoreTimes();
        $this->app->instance(RateLimiterService::class, $rateLimiterMock);

        Log::shouldReceive('info')->once();
        Log::shouldReceive('error')->zeroOrMoreTimes();

        $result = $this->connector->getPlayerSummaries(['76561198000000001']);

        $this->assertEquals([], $result);
    }

    public function test_get_player_summaries_returns_null_on_api_error()
    {
        Http::fake([
            'https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/*' => Http::response(['error' => 'Invalid request'], 400),
        ]);

        // Mock rate limiter
        $rateLimiterMock = Mockery::mock(RateLimiterService::class);
        $rateLimiterMock->shouldReceive('checkSteamApiLimit')->once()->andReturn(true);
        $rateLimiterMock->shouldReceive('waitForRateLimit')->zeroOrMoreTimes();
        $this->app->instance(RateLimiterService::class, $rateLimiterMock);

        Log::shouldReceive('warning')->once();

        $result = $this->connector->getPlayerSummaries(['76561198000000001']);

        $this->assertNull($result);
    }

    public function test_get_player_summaries_returns_null_on_exception()
    {
        Http::fake([
            'https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/*' => function () {
                throw new Exception('Network error');
            },
        ]);

        // Mock rate limiter
        $rateLimiterMock = Mockery::mock(RateLimiterService::class);
        $rateLimiterMock->shouldReceive('checkSteamApiLimit')->once()->andReturn(true);
        $rateLimiterMock->shouldReceive('waitForRateLimit')->zeroOrMoreTimes();
        $this->app->instance(RateLimiterService::class, $rateLimiterMock);

        Log::shouldReceive('error')->once();

        $result = $this->connector->getPlayerSummaries(['76561198000000001']);

        $this->assertNull($result);
    }

    public function test_check_rate_limit_waits_when_limit_exceeded()
    {
        // Mock rate limiter to return false (rate limit exceeded)
        $rateLimiterMock = Mockery::mock(RateLimiterService::class);
        $rateLimiterMock->shouldReceive('checkSteamApiLimit')->once()->andReturn(false);
        $rateLimiterMock->shouldReceive('waitForRateLimit')->with('steam_api', 100, 300)->once();
        $this->app->instance(RateLimiterService::class, $rateLimiterMock);

        Log::shouldReceive('warning')->once();
        Log::shouldReceive('info')->once();
        Log::shouldReceive('error')->zeroOrMoreTimes();

        Http::fake([
            'https://api.steampowered.com/ICSGOPlayers_730/GetNextMatchSharingCode/v1/*' => Http::response([
                'result' => [
                    'nextcode' => 'CSGO-ABC123-DEF456-GHI789-JKL012-MNO345',
                ],
            ], 200),
        ]);

        $result = $this->connector->getNextMatchSharingCode('76561198000000001', 'auth_code', 'known_code');

        $this->assertEquals('CSGO-ABC123-DEF456-GHI789-JKL012-MNO345', $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
