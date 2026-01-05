<?php

namespace Tests\Feature\Services\Integrations\Discord;

use App\Exceptions\DiscordAPIConnectorException;
use App\Services\Integrations\Discord\DiscordAPIConnector;
use App\Services\Integrations\Discord\DiscordRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class DiscordRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private DiscordRepository $repository;

    private $connectorMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connectorMock = Mockery::mock(DiscordAPIConnector::class);
        $this->repository = new DiscordRepository($this->connectorMock);
    }

    public function test_get_guild_channels_returns_sorted_channels(): void
    {
        $channels = [
            ['id' => '111', 'name' => 'general', 'type' => 0, 'position' => 2],
            ['id' => '222', 'name' => 'announcements', 'type' => 0, 'position' => 1],
            ['id' => '333', 'name' => 'random', 'type' => 0, 'position' => 3],
        ];

        $this->connectorMock->shouldReceive('get')
            ->with('guilds/123456789/channels', [])
            ->once()
            ->andReturn($channels);

        $result = $this->repository->getGuildChannels('123456789');

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertEquals('222', $result[0]['id']); // Should be sorted by position
        $this->assertEquals('111', $result[1]['id']);
        $this->assertEquals('333', $result[2]['id']);
    }

    public function test_get_guild_members_handles_pagination(): void
    {
        // First page returns exactly limit members, so pagination continues
        $firstPage = array_fill(0, 1000, ['user' => ['id' => '111']]);
        $firstPage[999] = ['user' => ['id' => '222']]; // Last one is 222

        $secondPage = [
            ['user' => ['id' => '333']],
        ];

        $this->connectorMock->shouldReceive('get')
            ->with('guilds/123456789/members', ['limit' => 1000])
            ->once()
            ->andReturn($firstPage);

        $this->connectorMock->shouldReceive('get')
            ->with('guilds/123456789/members', ['limit' => 1000, 'after' => '222'])
            ->once()
            ->andReturn($secondPage);

        $result = $this->repository->getGuildMembers('123456789', 1000);

        $this->assertIsArray($result);
        $this->assertGreaterThanOrEqual(2, count($result));
        $this->assertContains('111', $result);
        $this->assertContains('222', $result);
        $this->assertContains('333', $result);
    }

    public function test_get_guild_members_respects_safety_limit(): void
    {
        // Create a large number of members to test safety limit
        $members = [];
        for ($i = 1; $i <= 1000; $i++) {
            $members[] = ['user' => ['id' => (string) $i]];
        }

        $this->connectorMock->shouldReceive('get')
            ->andReturn($members);

        $result = $this->repository->getGuildMembers('123456789', 1000);

        $this->assertLessThanOrEqual(10000, count($result)); // Safety limit
    }

    public function test_send_message_sends_to_channel(): void
    {
        $messageData = [
            'content' => 'Test message',
            'embeds' => [],
        ];

        $response = [
            'id' => '999',
            'content' => 'Test message',
        ];

        $this->connectorMock->shouldReceive('post')
            ->with('channels/123456789/messages', $messageData)
            ->once()
            ->andReturn($response);

        $result = $this->repository->sendMessage('123456789', $messageData);

        $this->assertEquals($response, $result);
    }

    public function test_get_channel_returns_channel_data(): void
    {
        $channelData = [
            'id' => '123456789',
            'name' => 'general',
            'type' => 0,
        ];

        $this->connectorMock->shouldReceive('get')
            ->with('channels/123456789', [])
            ->once()
            ->andReturn($channelData);

        $result = $this->repository->getChannel('123456789');

        $this->assertEquals($channelData, $result);
    }

    public function test_get_guild_returns_guild_data(): void
    {
        $guildData = [
            'id' => '123456789',
            'name' => 'Test Guild',
        ];

        $this->connectorMock->shouldReceive('get')
            ->with('guilds/123456789', [])
            ->once()
            ->andReturn($guildData);

        $result = $this->repository->getGuild('123456789');

        $this->assertEquals($guildData, $result);
    }

    public function test_methods_handle_connector_exceptions(): void
    {
        $this->connectorMock->shouldReceive('get')
            ->andThrow(DiscordAPIConnectorException::serviceUnavailable('Test error'));

        $this->expectException(DiscordAPIConnectorException::class);

        $this->repository->getGuildChannels('123456789');
    }

    public function test_get_guild_members_returns_unique_user_ids(): void
    {
        $members = [
            ['user' => ['id' => '111']],
            ['user' => ['id' => '222']],
            ['user' => ['id' => '111']], // Duplicate
        ];

        $this->connectorMock->shouldReceive('get')
            ->andReturn($members);

        $result = $this->repository->getGuildMembers('123456789');

        $this->assertCount(2, $result); // Should be unique
        $this->assertContains('111', $result);
        $this->assertContains('222', $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
