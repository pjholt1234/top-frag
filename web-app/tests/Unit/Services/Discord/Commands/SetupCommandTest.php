<?php

namespace Tests\Unit\Services\Discord\Commands;

use App\Services\Clans\ClanService;
use App\Services\Discord\Commands\SetupCommand;
use App\Services\Discord\DiscordService;
use App\Services\Integrations\Discord\DiscordRepository;
use Mockery;
use Tests\TestCase;

class SetupCommandTest extends TestCase
{
    private SetupCommand $command;

    private $clanServiceMock;

    private $discordRepositoryMock;

    private $discordServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clanServiceMock = Mockery::mock(ClanService::class);
        $this->discordRepositoryMock = Mockery::mock(DiscordRepository::class);
        $this->discordServiceMock = Mockery::mock(DiscordService::class);
        $this->command = new SetupCommand(
            $this->clanServiceMock,
            $this->discordRepositoryMock,
            $this->discordServiceMock
        );
    }

    public function test_command_implements_interface(): void
    {
        $this->assertInstanceOf(\App\Services\Discord\Commands\CommandInterface::class, $this->command);
    }

    public function test_execute_returns_array(): void
    {
        $this->discordServiceMock->shouldReceive('errorResponse')
            ->once()
            ->andReturn(['type' => 4, 'data' => ['content' => 'Error']]);

        $result = $this->command->execute([]);

        $this->assertIsArray($result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
