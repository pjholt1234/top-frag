<?php

namespace Tests\Feature\Services\Discord\Commands;

use App\Models\Achievement;
use App\Models\GameMatch;
use App\Models\Player;
use App\Services\Discord\Commands\MatchReportCommand;
use App\Services\Discord\DiscordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class MatchReportCommandTest extends TestCase
{
    use RefreshDatabase;

    private MatchReportCommand $command;

    private $discordServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->discordServiceMock = Mockery::mock(DiscordService::class);
        $this->command = new MatchReportCommand($this->discordServiceMock);
    }

    public function test_execute_displays_match_report(): void
    {
        $match = GameMatch::factory()->create(['id' => 123]);
        $player = Player::factory()->create();
        $match->players()->attach($player->id, ['team' => 'A']);

        $embed = [
            'title' => 'Match Report #123',
            'description' => 'Test description',
        ];

        $this->discordServiceMock->shouldReceive('formatMatchReportEmbed')
            ->once()
            ->with(Mockery::type(GameMatch::class), Mockery::type('Illuminate\Database\Eloquent\Collection'))
            ->andReturn($embed);

        $payload = [
            'data' => [
                'options' => [
                    ['name' => 'id', 'value' => 123],
                ],
            ],
        ];

        $result = $this->command->execute($payload);

        $this->assertIsArray($result);
        $this->assertEquals(4, $result['type']);
        $this->assertArrayHasKey('embeds', $result['data']);
        $this->assertEquals($embed, $result['data']['embeds'][0]);
    }

    public function test_execute_handles_missing_match(): void
    {
        $payload = [
            'data' => [
                'options' => [
                    ['name' => 'id', 'value' => 99999],
                ],
            ],
        ];

        $this->discordServiceMock->shouldReceive('errorResponse')
            ->once()
            ->with('Match with ID 99999 not found.')
            ->andReturn(['type' => 4, 'data' => ['content' => 'Error']]);

        $result = $this->command->execute($payload);

        $this->assertIsArray($result);
    }

    public function test_execute_includes_achievements(): void
    {
        $match = GameMatch::factory()->create(['id' => 123]);
        $player = Player::factory()->create();
        $achievement = Achievement::factory()->create([
            'match_id' => 123,
            'player_id' => $player->id,
        ]);

        $embed = [
            'title' => 'Match Report #123',
            'fields' => [
                ['name' => '🏆 Achievements', 'value' => 'Test achievement'],
            ],
        ];

        $this->discordServiceMock->shouldReceive('formatMatchReportEmbed')
            ->once()
            ->andReturn($embed);

        $payload = [
            'data' => [
                'options' => [
                    ['name' => 'id', 'value' => 123],
                ],
            ],
        ];

        $result = $this->command->execute($payload);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('embeds', $result['data']);
    }

    public function test_execute_rejects_when_match_id_missing(): void
    {
        $payload = [
            'data' => [
                'options' => [],
            ],
        ];

        $this->discordServiceMock->shouldReceive('errorResponse')
            ->once()
            ->with('Match ID is required.')
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
