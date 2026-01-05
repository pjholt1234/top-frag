<?php

namespace Tests\Feature\Services\Discord;

use App\Models\Clan;
use App\Models\GameMatch;
use App\Models\Player;
use App\Services\Clans\ClanLeaderboardService;
use App\Services\Clans\ClanService;
use App\Services\Discord\Commands\SetupCommand;
use App\Services\Discord\DiscordService;
use App\Services\Integrations\Discord\DiscordRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class DiscordServiceTest extends TestCase
{
    use RefreshDatabase;

    private DiscordService $service;

    private $clanServiceMock;

    private $leaderboardServiceMock;

    private $discordRepositoryMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clanServiceMock = Mockery::mock(ClanService::class);
        $this->leaderboardServiceMock = Mockery::mock(ClanLeaderboardService::class);
        $this->discordRepositoryMock = Mockery::mock(DiscordRepository::class);

        $this->service = new DiscordService(
            $this->clanServiceMock,
            $this->leaderboardServiceMock,
            $this->discordRepositoryMock
        );
    }

    public function test_handle_interaction_routes_to_commands(): void
    {
        $setupCommandMock = Mockery::mock(SetupCommand::class);
        $setupCommandMock->shouldReceive('execute')
            ->once()
            ->andReturn(['type' => 4, 'data' => ['content' => 'Setup']]);

        $this->app->instance(SetupCommand::class, $setupCommandMock);

        $payload = [
            'type' => 2,
            'data' => ['name' => 'setup'],
            'guild_id' => '123456789',
        ];

        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();

        $result = $this->service->handleInteraction($payload);

        $this->assertIsArray($result);
    }

    public function test_send_match_report_to_discord(): void
    {
        $clan = Clan::factory()->create([
            'discord_guild_id' => '987654321',
            'discord_channel_id' => '111111111',
        ]);
        $match = GameMatch::factory()->create();
        $player = Player::factory()->create();
        $match->players()->attach($player->id, ['team' => 'A']);

        $this->discordRepositoryMock->shouldReceive('sendMessage')
            ->once()
            ->with('111111111', Mockery::type('array'))
            ->andReturn(['id' => '999']);

        Log::shouldReceive('info')->once();

        $this->service->sendMatchReportToDiscord($match, $clan);

        // Should not throw exception
        $this->assertTrue(true);
    }

    public function test_send_match_report_skips_when_not_configured(): void
    {
        $clan = Clan::factory()->create([
            'discord_guild_id' => null,
            'discord_channel_id' => null,
        ]);
        $match = GameMatch::factory()->create();

        Log::shouldReceive('info')->zeroOrMoreTimes();

        $this->service->sendMatchReportToDiscord($match, $clan);

        // Should not call sendMessage
        $this->discordRepositoryMock->shouldNotHaveReceived('sendMessage');
    }

    public function test_format_match_report_embed(): void
    {
        $match = GameMatch::factory()->create([
            'map' => 'de_dust2',
            'winning_team_score' => 16,
            'losing_team_score' => 14,
        ]);
        $player = Player::factory()->create(['name' => 'TestPlayer']);
        $match->players()->attach($player->id, ['team' => 'A']);

        $achievements = collect([]);

        $embed = $this->service->formatMatchReportEmbed($match, $achievements);

        $this->assertIsArray($embed);
        $this->assertArrayHasKey('title', $embed);
        $this->assertArrayHasKey('description', $embed);
        $this->assertStringContainsString('de_dust2', $embed['description']);
    }

    public function test_show_channel_selection_menu(): void
    {
        $clan = Clan::factory()->create(['name' => 'TestClan']);

        $this->discordRepositoryMock->shouldReceive('getGuildChannels')
            ->once()
            ->with('987654321')
            ->andReturn([
                ['id' => '111', 'name' => 'general', 'type' => 0, 'position' => 1],
                ['id' => '222', 'name' => 'announcements', 'type' => 0, 'position' => 2],
            ]);

        $result = $this->service->showChannelSelectionMenu('987654321', $clan);

        $this->assertIsArray($result);
        $this->assertEquals(4, $result['type']);
        $this->assertStringContainsString('TestClan', $result['data']['content']);
        $this->assertArrayHasKey('components', $result['data']);
    }

    public function test_handle_interaction_handles_ping(): void
    {
        $payload = ['type' => 1];

        Log::shouldReceive('info')->once();

        $result = $this->service->handleInteraction($payload);

        $this->assertEquals(['type' => 1], $result);
    }

    public function test_error_response_returns_correct_format(): void
    {
        $result = $this->service->errorResponse('Test error');

        $this->assertIsArray($result);
        $this->assertEquals(4, $result['type']);
        $this->assertStringContainsString('❌', $result['data']['content']);
        $this->assertStringContainsString('Test error', $result['data']['content']);
    }

    public function test_success_response_returns_correct_format(): void
    {
        $result = $this->service->successResponse('Test success');

        $this->assertIsArray($result);
        $this->assertEquals(4, $result['type']);
        $this->assertEquals('Test success', $result['data']['content']);
        $this->assertEquals(64, $result['data']['flags']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
