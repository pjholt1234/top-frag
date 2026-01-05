<?php

namespace Tests\Feature\Services\Discord\Commands;

use App\Enums\LeaderboardType;
use App\Models\Clan;
use App\Models\User;
use App\Services\Clans\ClanLeaderboardService;
use App\Services\Discord\Commands\LeaderboardCommand;
use App\Services\Discord\DiscordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class LeaderboardCommandTest extends TestCase
{
    use RefreshDatabase;

    private LeaderboardCommand $command;

    private $leaderboardServiceMock;

    private $discordServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->leaderboardServiceMock = Mockery::mock(ClanLeaderboardService::class);
        $this->discordServiceMock = Mockery::mock(DiscordService::class);
        $this->command = new LeaderboardCommand($this->leaderboardServiceMock, $this->discordServiceMock);
    }

    public function test_execute_displays_leaderboard(): void
    {
        $user = User::factory()->create(['name' => 'TestUser']);
        $clan = Clan::factory()->create(['discord_guild_id' => '987654321']);

        $leaderboardEntry = (object) [
            'position' => 1,
            'value' => 100.5,
            'user' => $user,
        ];

        $leaderboard = new Collection([$leaderboardEntry]);

        $this->leaderboardServiceMock->shouldReceive('getLeaderboard')
            ->once()
            ->andReturn($leaderboard);

        $payload = [
            'guild_id' => '987654321',
            'data' => [
                'options' => [
                    ['name' => 'leaderboard_type', 'value' => LeaderboardType::AIM->value],
                ],
            ],
        ];

        $result = $this->command->execute($payload);

        $this->assertIsArray($result);
        $this->assertEquals(4, $result['type']);
        $this->assertArrayHasKey('embeds', $result['data']);
        $this->assertStringContainsString('TestUser', $result['data']['embeds'][0]['fields'][0]['name']);
    }

    public function test_execute_handles_empty_leaderboard(): void
    {
        $clan = Clan::factory()->create(['discord_guild_id' => '987654321']);

        $this->leaderboardServiceMock->shouldReceive('getLeaderboard')
            ->once()
            ->andReturn(new Collection);

        $this->discordServiceMock->shouldReceive('successResponse')
            ->once()
            ->with(Mockery::pattern('/No leaderboard data available/'))
            ->andReturn(['type' => 4, 'data' => ['content' => 'No data']]);

        $payload = [
            'guild_id' => '987654321',
            'data' => [
                'options' => [
                    ['name' => 'leaderboard_type', 'value' => LeaderboardType::AIM->value],
                ],
            ],
        ];

        $result = $this->command->execute($payload);

        $this->assertIsArray($result);
    }

    public function test_execute_validates_leaderboard_type(): void
    {
        $payload = [
            'guild_id' => '987654321',
            'data' => [
                'options' => [
                    ['name' => 'leaderboard_type', 'value' => 'invalid_type'],
                ],
            ],
        ];

        $this->discordServiceMock->shouldReceive('errorResponse')
            ->once()
            ->with(Mockery::pattern('/Invalid leaderboard type/'))
            ->andReturn(['type' => 4, 'data' => ['content' => 'Error']]);

        $result = $this->command->execute($payload);

        $this->assertIsArray($result);
    }

    public function test_execute_rejects_when_leaderboard_type_missing(): void
    {
        $payload = [
            'guild_id' => '987654321',
            'data' => [
                'options' => [],
            ],
        ];

        $this->discordServiceMock->shouldReceive('errorResponse')
            ->once()
            ->with('Leaderboard type is required.')
            ->andReturn(['type' => 4, 'data' => ['content' => 'Error']]);

        $result = $this->command->execute($payload);

        $this->assertIsArray($result);
    }

    public function test_execute_rejects_when_not_in_guild(): void
    {
        $payload = [];

        $this->discordServiceMock->shouldReceive('errorResponse')
            ->once()
            ->with('This command can only be used in a Discord server.')
            ->andReturn(['type' => 4, 'data' => ['content' => 'Error']]);

        $result = $this->command->execute($payload);

        $this->assertIsArray($result);
    }

    public function test_execute_rejects_when_no_clan_linked(): void
    {
        $payload = [
            'guild_id' => '987654321',
            'data' => [
                'options' => [
                    ['name' => 'leaderboard_type', 'value' => LeaderboardType::AIM->value],
                ],
            ],
        ];

        $this->discordServiceMock->shouldReceive('errorResponse')
            ->once()
            ->with('This Discord server is not linked to any clan.')
            ->andReturn(['type' => 4, 'data' => ['content' => 'Error']]);

        $result = $this->command->execute($payload);

        $this->assertIsArray($result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
