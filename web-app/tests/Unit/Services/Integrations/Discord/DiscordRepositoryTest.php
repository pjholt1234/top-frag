<?php

namespace Tests\Unit\Services\Integrations\Discord;

use App\Exceptions\DiscordAPIConnectorException;
use App\Services\Integrations\Discord\DiscordAPIConnector;
use App\Services\Integrations\Discord\DiscordRepository;
use Mockery;
use Tests\TestCase;

class DiscordRepositoryTest extends TestCase
{
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
            ['id' => '111', 'position' => 2],
            ['id' => '222', 'position' => 1],
            ['id' => '333', 'position' => 3],
        ];

        $this->connectorMock->shouldReceive('get')
            ->once()
            ->andReturn($channels);

        $result = $this->repository->getGuildChannels('123456789');

        $this->assertEquals('222', $result[0]['id']); // Should be sorted
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

        $result = $this->repository->getGuildMembers('123456789');

        $this->assertGreaterThanOrEqual(2, count($result));
        $this->assertContains('111', $result);
        $this->assertContains('222', $result);
        $this->assertContains('333', $result);
    }

    public function test_send_message_sends_to_channel(): void
    {
        $messageData = ['content' => 'Test'];
        $response = ['id' => '999'];

        $this->connectorMock->shouldReceive('post')
            ->once()
            ->with('channels/123456789/messages', $messageData)
            ->andReturn($response);

        $result = $this->repository->sendMessage('123456789', $messageData);

        $this->assertEquals($response, $result);
    }

    public function test_get_channel_returns_channel_data(): void
    {
        $channelData = ['id' => '123', 'name' => 'general'];

        $this->connectorMock->shouldReceive('get')
            ->once()
            ->andReturn($channelData);

        $result = $this->repository->getChannel('123');

        $this->assertEquals($channelData, $result);
    }

    public function test_get_guild_returns_guild_data(): void
    {
        $guildData = ['id' => '123', 'name' => 'Test Guild'];

        $this->connectorMock->shouldReceive('get')
            ->once()
            ->andReturn($guildData);

        $result = $this->repository->getGuild('123');

        $this->assertEquals($guildData, $result);
    }

    public function test_methods_propagate_connector_exceptions(): void
    {
        $this->connectorMock->shouldReceive('get')
            ->andThrow(DiscordAPIConnectorException::serviceUnavailable());

        $this->expectException(DiscordAPIConnectorException::class);

        $this->repository->getGuildChannels('123');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
