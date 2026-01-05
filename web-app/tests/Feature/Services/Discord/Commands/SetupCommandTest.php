<?php

namespace Tests\Feature\Services\Discord\Commands;

use App\Models\Clan;
use App\Models\User;
use App\Services\Clans\ClanService;
use App\Services\Discord\Commands\SetupCommand;
use App\Services\Discord\DiscordService;
use App\Services\Integrations\Discord\DiscordRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SetupCommandTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_execute_shows_setup_options(): void
    {
        $user = User::factory()->create(['discord_id' => '123456789']);

        $payload = [
            'guild_id' => '987654321',
            'guild' => ['name' => 'Test Guild'],
            'member' => ['user' => ['id' => '123456789']],
        ];

        $result = $this->command->execute($payload);

        $this->assertIsArray($result);
        $this->assertEquals(4, $result['type']);
        $this->assertStringContainsString('Setup Top Frag Clan', $result['data']['content']);
        $this->assertArrayHasKey('components', $result['data']);
    }

    public function test_execute_handles_create_new_option(): void
    {
        $user = User::factory()->create(['discord_id' => '123456789']);

        $payload = [
            'guild_id' => '987654321',
            'member' => ['user' => ['id' => '123456789']],
        ];

        $result = $this->command->handleSetupOption($payload, 'create_new');

        $this->assertIsArray($result);
        $this->assertEquals(9, $result['type']); // MODAL
        $this->assertEquals('Create New Clan', $result['data']['title']);
    }

    public function test_execute_handles_link_existing_option(): void
    {
        $user = User::factory()->create(['discord_id' => '123456789']);
        $clan = Clan::factory()->create([
            'owned_by' => $user->id,
            'discord_guild_id' => null,
        ]);

        $payload = [
            'guild_id' => '987654321',
            'member' => ['user' => ['id' => '123456789']],
        ];

        $result = $this->command->handleSetupOption($payload, 'link_existing');

        $this->assertIsArray($result);
        $this->assertEquals(4, $result['type']);
        $this->assertStringContainsString('Link Existing Clan', $result['data']['content']);
    }

    public function test_execute_validates_clan_name_and_tag(): void
    {
        $user = User::factory()->create(['discord_id' => '123456789']);

        $components = [
            [
                'components' => [
                    ['custom_id' => 'clan_name', 'value' => 'TestClan'],
                    ['custom_id' => 'clan_tag', 'value' => 'TC'],
                ],
            ],
        ];

        $this->discordServiceMock->shouldReceive('validateClanName')
            ->once()
            ->with('TestClan')
            ->andReturn(null);

        $this->discordServiceMock->shouldReceive('validateClanTag')
            ->once()
            ->with('TC')
            ->andReturn(null);

        $this->discordServiceMock->shouldReceive('handleBotInstallation')
            ->once()
            ->andReturn(['type' => 4, 'data' => ['content' => 'Success']]);

        $payload = [
            'guild_id' => '987654321',
            'guild' => ['name' => 'Test Guild'],
            'member' => ['user' => ['id' => '123456789']],
            'data' => ['components' => $components],
        ];

        $result = $this->command->handleCreateClanModal($payload, $components);

        $this->assertIsArray($result);
    }

    public function test_execute_handles_modal_submission(): void
    {
        $user = User::factory()->create(['discord_id' => '123456789']);

        $components = [
            [
                'components' => [
                    ['custom_id' => 'clan_name', 'value' => 'TestClan'],
                    ['custom_id' => 'clan_tag', 'value' => 'TC'],
                ],
            ],
        ];

        $this->discordServiceMock->shouldReceive('validateClanName')
            ->once()
            ->andReturn(null);

        $this->discordServiceMock->shouldReceive('validateClanTag')
            ->once()
            ->andReturn(null);

        $this->discordServiceMock->shouldReceive('handleBotInstallation')
            ->once()
            ->andReturn(['type' => 4, 'data' => ['content' => 'Success']]);

        $payload = [
            'guild_id' => '987654321',
            'guild' => ['name' => 'Test Guild'],
            'member' => ['user' => ['id' => '123456789']],
        ];

        $result = $this->command->handleCreateClanModal($payload, $components);

        $this->assertIsArray($result);
    }

    public function test_execute_rejects_when_user_not_found(): void
    {
        $payload = [
            'guild_id' => '987654321',
            'guild' => ['name' => 'Test Guild'],
            'member' => ['user' => ['id' => '999999999']],
        ];

        $this->discordServiceMock->shouldReceive('errorResponse')
            ->once()
            ->with('You must be a Top Frag member with a linked Discord account to use this command.')
            ->andReturn(['type' => 4, 'data' => ['content' => 'Error']]);

        $result = $this->command->execute($payload);

        $this->assertIsArray($result);
    }

    public function test_execute_rejects_when_clan_already_linked(): void
    {
        $user = User::factory()->create(['discord_id' => '123456789']);
        $clan = Clan::factory()->create(['discord_guild_id' => '987654321']);

        $payload = [
            'guild_id' => '987654321',
            'guild' => ['name' => 'Test Guild'],
            'member' => ['user' => ['id' => '123456789']],
        ];

        $this->discordServiceMock->shouldReceive('errorResponse')
            ->once()
            ->with(Mockery::pattern('/already linked/'))
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
