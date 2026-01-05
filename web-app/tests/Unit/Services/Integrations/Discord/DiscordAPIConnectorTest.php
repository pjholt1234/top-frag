<?php

namespace Tests\Unit\Services\Integrations\Discord;

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

        config(['services.discord.bot_token' => 'test_bot_token']);

        $this->connector = new DiscordAPIConnector;
    }

    public function test_get_returns_data_on_success(): void
    {
        Http::fake([
            'https://discord.com/api/v10/test' => Http::response(['data' => 'test'], 200),
        ]);

        $result = $this->connector->get('test');

        $this->assertIsArray($result);
        $this->assertEquals('test', $result['data']);
    }

    public function test_post_returns_data_on_success(): void
    {
        Http::fake([
            'https://discord.com/api/v10/test' => Http::response(['id' => '123'], 200),
        ]);

        $result = $this->connector->post('test', ['content' => 'test']);

        $this->assertIsArray($result);
        $this->assertEquals('123', $result['id']);
    }

    public function test_get_throws_exception_on_400(): void
    {
        Http::fake([
            'https://discord.com/api/v10/test' => Http::response(['message' => 'Bad request'], 400),
        ]);

        Log::shouldReceive('error')->once();

        $this->expectException(DiscordAPIConnectorException::class);

        $this->connector->get('test');
    }

    public function test_get_throws_exception_on_401(): void
    {
        Http::fake([
            'https://discord.com/api/v10/test' => Http::response(['message' => 'Unauthorized'], 401),
        ]);

        Log::shouldReceive('error')->once();

        $this->expectException(DiscordAPIConnectorException::class);

        $this->connector->get('test');
    }

    public function test_get_throws_exception_on_403(): void
    {
        Http::fake([
            'https://discord.com/api/v10/test' => Http::response(['message' => 'Forbidden'], 403),
        ]);

        Log::shouldReceive('error')->once();

        $this->expectException(DiscordAPIConnectorException::class);

        $this->connector->get('test');
    }

    public function test_get_throws_exception_on_404(): void
    {
        Http::fake([
            'https://discord.com/api/v10/test' => Http::response(['message' => 'Not found'], 404),
        ]);

        Log::shouldReceive('error')->once();

        $this->expectException(DiscordAPIConnectorException::class);

        $this->connector->get('test');
    }

    public function test_get_throws_exception_on_429(): void
    {
        Http::fake([
            'https://discord.com/api/v10/test' => Http::response(['message' => 'Rate limited'], 429),
        ]);

        Log::shouldReceive('error')->once();

        $this->expectException(DiscordAPIConnectorException::class);

        $this->connector->get('test');
    }

    public function test_get_throws_exception_on_503(): void
    {
        Http::fake([
            'https://discord.com/api/v10/test' => Http::response(['message' => 'Service unavailable'], 503),
        ]);

        Log::shouldReceive('error')->once();

        $this->expectException(DiscordAPIConnectorException::class);

        $this->connector->get('test');
    }

    public function test_get_throws_exception_on_network_error(): void
    {
        Http::fake([
            'https://discord.com/api/v10/test' => function () {
                throw new Exception('Network error');
            },
        ]);

        Log::shouldReceive('error')->once();

        $this->expectException(DiscordAPIConnectorException::class);

        $this->connector->get('test');
    }

    public function test_constructor_throws_when_token_missing(): void
    {
        config(['services.discord.bot_token' => null]);

        Log::shouldReceive('error')->once();

        $this->expectException(DiscordAPIConnectorException::class);

        new DiscordAPIConnector;
    }
}
