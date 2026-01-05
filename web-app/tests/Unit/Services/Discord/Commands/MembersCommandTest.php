<?php

namespace Tests\Unit\Services\Discord\Commands;

use App\Services\Discord\Commands\MembersCommand;
use App\Services\Discord\DiscordService;
use Mockery;
use Tests\TestCase;

class MembersCommandTest extends TestCase
{
    private MembersCommand $command;

    private $discordServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->discordServiceMock = Mockery::mock(DiscordService::class);
        $this->command = new MembersCommand($this->discordServiceMock);
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
