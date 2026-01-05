<?php

namespace Tests\Feature\Controllers\Api;

use App\Services\Discord\DiscordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class DiscordWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private $discordServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->discordServiceMock = Mockery::mock(DiscordService::class);
        $this->app->instance(DiscordService::class, $this->discordServiceMock);

        // Bypass Discord signature verification in tests
        $this->withoutMiddleware(\App\Http\Middleware\VerifyDiscordSignature::class);
    }

    public function test_handles_ping_interaction(): void
    {
        // PING is handled directly by the controller, not via handleInteraction
        $response = $this->postJson('/api/discord/webhook', [
            'type' => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['type' => 1]);
    }

    public function test_handles_setup_command(): void
    {
        $this->discordServiceMock->shouldReceive('handleInteraction')
            ->once()
            ->andReturn([
                'type' => 4,
                'data' => ['content' => 'Setup options'],
            ]);

        $response = $this->postJson('/api/discord/webhook', [
            'type' => 2,
            'data' => ['name' => 'setup'],
            'guild_id' => '123456789',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['type' => 4]);
    }

    public function test_handles_unlink_clan_command(): void
    {
        $this->discordServiceMock->shouldReceive('handleInteraction')
            ->once()
            ->andReturn([
                'type' => 4,
                'data' => ['content' => 'Clan unlinked'],
            ]);

        $response = $this->postJson('/api/discord/webhook', [
            'type' => 2,
            'data' => ['name' => 'unlink-clan'],
            'guild_id' => '123456789',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['type' => 4]);
    }

    public function test_handles_members_command(): void
    {
        $this->discordServiceMock->shouldReceive('handleInteraction')
            ->once()
            ->andReturn([
                'type' => 4,
                'data' => ['content' => 'Members list'],
            ]);

        $response = $this->postJson('/api/discord/webhook', [
            'type' => 2,
            'data' => ['name' => 'members'],
            'guild_id' => '123456789',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['type' => 4]);
    }

    public function test_handles_leaderboard_command(): void
    {
        $this->discordServiceMock->shouldReceive('handleInteraction')
            ->once()
            ->andReturn([
                'type' => 4,
                'data' => ['embeds' => []],
            ]);

        $response = $this->postJson('/api/discord/webhook', [
            'type' => 2,
            'data' => [
                'name' => 'leaderboard',
                'options' => [
                    ['name' => 'leaderboard_type', 'value' => 'kills'],
                ],
            ],
            'guild_id' => '123456789',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['type' => 4]);
    }

    public function test_handles_match_report_command(): void
    {
        $this->discordServiceMock->shouldReceive('handleInteraction')
            ->once()
            ->andReturn([
                'type' => 4,
                'data' => ['embeds' => []],
            ]);

        $response = $this->postJson('/api/discord/webhook', [
            'type' => 2,
            'data' => [
                'name' => 'match-report',
                'options' => [
                    ['name' => 'id', 'value' => 123],
                ],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['type' => 4]);
    }

    public function test_handles_message_component_interaction(): void
    {
        $this->discordServiceMock->shouldReceive('handleInteraction')
            ->once()
            ->andReturn([
                'type' => 4,
                'data' => ['content' => 'Component handled'],
            ]);

        $response = $this->postJson('/api/discord/webhook', [
            'type' => 3,
            'data' => [
                'custom_id' => 'setup_option',
                'values' => ['create_new'],
            ],
            'guild_id' => '123456789',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['type' => 4]);
    }

    public function test_handles_modal_submit_interaction(): void
    {
        $this->discordServiceMock->shouldReceive('handleInteraction')
            ->once()
            ->andReturn([
                'type' => 4,
                'data' => ['content' => 'Modal submitted'],
            ]);

        $response = $this->postJson('/api/discord/webhook', [
            'type' => 5,
            'data' => [
                'custom_id' => 'create_clan_modal',
                'components' => [],
            ],
            'guild_id' => '123456789',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['type' => 4]);
    }

    public function test_handles_bot_installation(): void
    {
        $this->discordServiceMock->shouldReceive('handleBotInstallation')
            ->once()
            ->with('987654321', 'Test Guild', '123456789')
            ->andReturn([
                'type' => 4,
                'data' => ['content' => 'Bot installed'],
            ]);

        // Bot installation is detected when there's no type but guild_id and member.user.id exist
        $response = $this->postJson('/api/discord/webhook', [
            'guild_id' => '987654321',
            'guild' => ['name' => 'Test Guild'],
            'member' => ['user' => ['id' => '123456789']],
            // No 'type' field - this triggers bot installation check
        ]);

        $response->assertStatus(200);
        $response->assertJson(['type' => 4]);
    }

    public function test_returns_error_for_unknown_interaction(): void
    {
        $this->discordServiceMock->shouldReceive('handleInteraction')
            ->once()
            ->andReturn(['type' => 1]);

        $response = $this->postJson('/api/discord/webhook', [
            'type' => 99, // Unknown type
        ]);

        $response->assertStatus(200);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
