<?php

namespace Tests\Feature\Services\Integrations\Discord;

use App\Exceptions\DiscordAPIConnectorException;
use App\Services\Integrations\Discord\DiscordAPIConnector;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class DiscordAPIConnectorTest extends TestCase
{
    use RefreshDatabase;

    private DiscordAPIConnector $connector;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up config for Discord bot token
        config(['services.discord.bot_token' => 'test_bot_token']);

        $this->connector = new DiscordAPIConnector;
    }

    public function test_get_returns_data_on_success(): void
    {
        Http::fake([
            'https://discord.com/api/v10/guilds/123456789/channels' => Http::response([
                ['id' => '111', 'name' => 'general', 'type' => 0],
                ['id' => '222', 'name' => 'announcements', 'type' => 0],
            ], 200),
        ]);

        $result = $this->connector->get('guilds/123456789/channels');

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('111', $result[0]['id']);
        $this->assertEquals('general', $result[0]['name']);
    }

    public function test_post_returns_data_on_success(): void
    {
        Http::fake([
            'https://discord.com/api/v10/channels/123456789/messages' => Http::response([
                'id' => '999',
                'content' => 'Test message',
            ], 200),
        ]);

        $result = $this->connector->post('channels/123456789/messages', [
            'content' => 'Test message',
        ]);

        $this->assertIsArray($result);
        $this->assertEquals('999', $result['id']);
        $this->assertEquals('Test message', $result['content']);
    }

    public function test_get_throws_exception_on_400(): void
    {
        Http::fake([
            'https://discord.com/api/v10/guilds/123456789/channels' => Http::response([
                'message' => 'Bad request',
            ], 400),
        ]);

        Log::shouldReceive('error')->once();

        $this->expectException(DiscordAPIConnectorException::class);
        $this->expectExceptionMessage('Discord API bad request: Bad request');

        $this->connector->get('guilds/123456789/channels');
    }

    public function test_get_throws_exception_on_401(): void
    {
        Http::fake([
            'https://discord.com/api/v10/guilds/123456789/channels' => Http::response([
                'message' => 'Unauthorized',
            ], 401),
        ]);

        Log::shouldReceive('error')->once();

        $this->expectException(DiscordAPIConnectorException::class);
        $this->expectExceptionMessage('Discord API authentication failed: Unauthorized');

        $this->connector->get('guilds/123456789/channels');
    }

    public function test_get_throws_exception_on_403(): void
    {
        Http::fake([
            'https://discord.com/api/v10/guilds/123456789/channels' => Http::response([
                'message' => 'Forbidden',
            ], 403),
        ]);

        Log::shouldReceive('error')->once();

        $this->expectException(DiscordAPIConnectorException::class);
        $this->expectExceptionMessage('Discord API access forbidden: Forbidden');

        $this->connector->get('guilds/123456789/channels');
    }

    public function test_get_throws_exception_on_404(): void
    {
        Http::fake([
            'https://discord.com/api/v10/guilds/123456789/channels' => Http::response([
                'message' => 'Not found',
            ], 404),
        ]);

        Log::shouldReceive('error')->once();

        $this->expectException(DiscordAPIConnectorException::class);
        $this->expectExceptionMessage('Discord API resource not found: Not found');

        $this->connector->get('guilds/123456789/channels');
    }

    public function test_get_throws_exception_on_429(): void
    {
        Http::fake([
            'https://discord.com/api/v10/guilds/123456789/channels' => Http::response([
                'message' => 'Rate limited',
            ], 429),
        ]);

        Log::shouldReceive('error')->once();

        $this->expectException(DiscordAPIConnectorException::class);
        $this->expectExceptionMessage('Discord API rate limit exceeded: Rate limited');

        $this->connector->get('guilds/123456789/channels');
    }

    public function test_get_throws_exception_on_503(): void
    {
        Http::fake([
            'https://discord.com/api/v10/guilds/123456789/channels' => Http::response([
                'message' => 'Service unavailable',
            ], 503),
        ]);

        Log::shouldReceive('error')->once();

        $this->expectException(DiscordAPIConnectorException::class);
        $this->expectExceptionMessage('Discord API service is unavailable: Service unavailable');

        $this->connector->get('guilds/123456789/channels');
    }

    public function test_get_throws_exception_on_network_error(): void
    {
        Http::fake([
            'https://discord.com/api/v10/guilds/123456789/channels' => function () {
                throw new Exception('Network error');
            },
        ]);

        Log::shouldReceive('error')->once();

        $this->expectException(DiscordAPIConnectorException::class);
        $this->expectExceptionMessage('Discord API request failed: Network error: Network error');

        $this->connector->get('guilds/123456789/channels');
    }

    public function test_constructor_throws_when_token_missing(): void
    {
        config(['services.discord.bot_token' => null]);

        Log::shouldReceive('error')->once();

        $this->expectException(DiscordAPIConnectorException::class);
        $this->expectExceptionMessage('Discord API configuration error: Missing or invalid \'services.discord.bot_token\'');

        new DiscordAPIConnector;
    }

    public function test_get_error_message_extracts_from_response(): void
    {
        Http::fake([
            'https://discord.com/api/v10/guilds/123456789/channels' => Http::response([
                'message' => 'Custom error message',
            ], 400),
        ]);

        Log::shouldReceive('error')->once();

        $this->expectException(DiscordAPIConnectorException::class);
        $this->expectExceptionMessage('Discord API bad request: Custom error message');

        $this->connector->get('guilds/123456789/channels');
    }

    public function test_get_error_message_handles_errors_array(): void
    {
        Http::fake([
            'https://discord.com/api/v10/guilds/123456789/channels' => Http::response([
                'errors' => [
                    'guild_id' => ['Invalid guild ID'],
                ],
            ], 400),
        ]);

        Log::shouldReceive('error')->once();

        $this->expectException(DiscordAPIConnectorException::class);
        $this->expectExceptionMessageMatches('/Discord API bad request/');

        $this->connector->get('guilds/123456789/channels');
    }

    public function test_get_returns_empty_array_when_response_is_not_array(): void
    {
        Http::fake([
            'https://discord.com/api/v10/guilds/123456789/channels' => Http::response('not an array', 200),
        ]);

        $result = $this->connector->get('guilds/123456789/channels');

        $this->assertEquals([], $result);
    }
}
