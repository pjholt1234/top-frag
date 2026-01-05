<?php

namespace Tests\Feature\Services\Discord\Commands;

use App\Models\Clan;
use App\Models\ClanMember;
use App\Models\User;
use App\Services\Discord\Commands\MembersCommand;
use App\Services\Discord\DiscordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class MembersCommandTest extends TestCase
{
    use RefreshDatabase;

    private MembersCommand $command;

    private $discordServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->discordServiceMock = Mockery::mock(DiscordService::class);
        $this->command = new MembersCommand($this->discordServiceMock);
    }

    public function test_execute_lists_clan_members(): void
    {
        $user1 = User::factory()->create(['discord_id' => '111111111', 'name' => 'User1']);
        $user2 = User::factory()->create(['discord_id' => null, 'name' => 'User2']);
        $clan = Clan::factory()->create(['discord_guild_id' => '987654321']);

        ClanMember::factory()->create(['clan_id' => $clan->id, 'user_id' => $user1->id]);
        ClanMember::factory()->create(['clan_id' => $clan->id, 'user_id' => $user2->id]);

        $payload = [
            'guild_id' => '987654321',
        ];

        $result = $this->command->execute($payload);

        $this->assertIsArray($result);
        $this->assertEquals(4, $result['type']);
        $this->assertStringContainsString('Clan Members', $result['data']['content']);
        $this->assertStringContainsString('User1', $result['data']['content']);
        $this->assertStringContainsString('User2', $result['data']['content']);
    }

    public function test_execute_shows_discord_linked_status(): void
    {
        $user1 = User::factory()->create(['discord_id' => '111111111', 'name' => 'User1']);
        $user2 = User::factory()->create(['discord_id' => null, 'name' => 'User2']);
        $clan = Clan::factory()->create(['discord_guild_id' => '987654321']);

        ClanMember::factory()->create(['clan_id' => $clan->id, 'user_id' => $user1->id]);
        ClanMember::factory()->create(['clan_id' => $clan->id, 'user_id' => $user2->id]);

        $payload = [
            'guild_id' => '987654321',
        ];

        $result = $this->command->execute($payload);

        $this->assertStringContainsString('✅', $result['data']['content']); // User1 has Discord
        $this->assertStringContainsString('❌', $result['data']['content']); // User2 doesn't have Discord
    }

    public function test_execute_handles_empty_clan(): void
    {
        $clan = Clan::factory()->create(['discord_guild_id' => '987654321']);

        $payload = [
            'guild_id' => '987654321',
        ];

        $this->discordServiceMock->shouldReceive('successResponse')
            ->once()
            ->with('This clan has no members yet.')
            ->andReturn(['type' => 4, 'data' => ['content' => 'No members']]);

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
