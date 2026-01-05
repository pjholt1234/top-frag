<?php

namespace Tests\Feature\Services\Discord\Commands;

use App\Models\Clan;
use App\Models\User;
use App\Services\Discord\Commands\UnlinkClanCommand;
use App\Services\Discord\DiscordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class UnlinkClanCommandTest extends TestCase
{
    use RefreshDatabase;

    private UnlinkClanCommand $command;

    private $discordServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->discordServiceMock = Mockery::mock(DiscordService::class);
        $this->command = new UnlinkClanCommand($this->discordServiceMock);
    }

    public function test_execute_unlinks_clan_when_user_is_owner(): void
    {
        $user = User::factory()->create(['discord_id' => '123456789']);
        $clan = Clan::factory()->create([
            'owned_by' => $user->id,
            'discord_guild_id' => '987654321',
        ]);

        $payload = [
            'guild_id' => '987654321',
            'member' => ['user' => ['id' => '123456789']],
        ];

        $this->discordServiceMock->shouldReceive('successResponse')
            ->once()
            ->with(Mockery::pattern('/has been unlinked/'))
            ->andReturn(['type' => 4, 'data' => ['content' => 'Success']]);

        $result = $this->command->execute($payload);

        $this->assertIsArray($result);
        $clan->refresh();
        $this->assertNull($clan->discord_guild_id);
    }

    public function test_execute_rejects_when_user_not_owner(): void
    {
        $owner = User::factory()->create(['discord_id' => '111111111']);
        $user = User::factory()->create(['discord_id' => '123456789']);
        $clan = Clan::factory()->create([
            'owned_by' => $owner->id,
            'discord_guild_id' => '987654321',
        ]);

        $payload = [
            'guild_id' => '987654321',
            'member' => ['user' => ['id' => '123456789']],
        ];

        $this->discordServiceMock->shouldReceive('errorResponse')
            ->once()
            ->with('Only the clan owner can unlink the clan from Discord.')
            ->andReturn(['type' => 4, 'data' => ['content' => 'Error']]);

        $result = $this->command->execute($payload);

        $this->assertIsArray($result);
        $clan->refresh();
        $this->assertEquals('987654321', $clan->discord_guild_id); // Should not be unlinked
    }

    public function test_execute_rejects_when_no_clan_linked(): void
    {
        $user = User::factory()->create(['discord_id' => '123456789']);

        $payload = [
            'guild_id' => '987654321',
            'member' => ['user' => ['id' => '123456789']],
        ];

        $this->discordServiceMock->shouldReceive('errorResponse')
            ->once()
            ->with('This Discord server is not linked to any clan.')
            ->andReturn(['type' => 4, 'data' => ['content' => 'Error']]);

        $result = $this->command->execute($payload);

        $this->assertIsArray($result);
    }

    public function test_execute_rejects_when_user_not_found(): void
    {
        $payload = [
            'guild_id' => '987654321',
            'member' => ['user' => ['id' => '999999999']],
        ];

        $this->discordServiceMock->shouldReceive('errorResponse')
            ->once()
            ->with('You must be a Top Frag member with a linked Discord account to use this command.')
            ->andReturn(['type' => 4, 'data' => ['content' => 'Error']]);

        $result = $this->command->execute($payload);

        $this->assertIsArray($result);
    }

    public function test_execute_rejects_when_not_in_guild(): void
    {
        $payload = [
            'member' => ['user' => ['id' => '123456789']],
        ];

        $this->discordServiceMock->shouldReceive('errorResponse')
            ->once()
            ->with('This command can only be used in a Discord server.')
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
