<?php

namespace Tests\Unit\Services;

use App\Exceptions\FaceITAPIConnectorException;
use App\Services\FaceITAPIConnector;
use App\Services\FaceITRepository;
use Mockery;
use Tests\TestCase;

class FaceITRepositoryTest extends TestCase
{
    private FaceITRepository $repository;

    private FaceITAPIConnector $connector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connector = Mockery::mock(FaceITAPIConnector::class);
        $this->repository = new FaceITRepository($this->connector);
    }

    public function test_get_player_by_steam_id_returns_player_data(): void
    {
        $steamId = '76561198081165057';
        $game = 'cs2';
        $expectedData = [
            'player_id' => '12345',
            'nickname' => 'TestPlayer',
            'steam_id_64' => $steamId,
            'games' => [
                'cs2' => [
                    'skill_level' => 5,
                    'faceit_elo' => 1500,
                ],
            ],
        ];

        $this->connector
            ->shouldReceive('get')
            ->once()
            ->with('players', [
                'game_player_id' => $steamId,
                'game' => $game,
            ])
            ->andReturn($expectedData);

        $result = $this->repository->getPlayerBySteamId($steamId, $game);

        $this->assertIsArray($result);
        $this->assertEquals($expectedData, $result);
        $this->assertEquals('12345', $result['player_id']);
        $this->assertEquals('TestPlayer', $result['nickname']);
    }

    public function test_get_player_by_steam_id_uses_default_game_cs2(): void
    {
        $steamId = '76561198081165057';
        $expectedData = [
            'player_id' => '12345',
            'nickname' => 'TestPlayer',
        ];

        $this->connector
            ->shouldReceive('get')
            ->once()
            ->with('players', [
                'game_player_id' => $steamId,
                'game' => 'cs2',
            ])
            ->andReturn($expectedData);

        $result = $this->repository->getPlayerBySteamId($steamId);

        $this->assertEquals($expectedData, $result);
    }

    public function test_get_player_by_steam_id_throws_exception_when_connector_fails(): void
    {
        $steamId = '76561198081165057';
        $game = 'cs2';

        $this->connector
            ->shouldReceive('get')
            ->once()
            ->with('players', [
                'game_player_id' => $steamId,
                'game' => $game,
            ])
            ->andThrow(FaceITAPIConnectorException::notFound('Player not found'));

        $this->expectException(FaceITAPIConnectorException::class);
        $this->expectExceptionMessage('Player not found');

        $this->repository->getPlayerBySteamId($steamId, $game);
    }

    public function test_get_player_by_nickname_returns_player_data(): void
    {
        $nickname = 'Twistzz';
        $expectedData = [
            'player_id' => '67890',
            'nickname' => $nickname,
            'avatar' => 'https://example.com/avatar.jpg',
            'games' => [
                'cs2' => [
                    'skill_level' => 10,
                    'faceit_elo' => 2500,
                ],
            ],
        ];

        $this->connector
            ->shouldReceive('get')
            ->once()
            ->with('players', [
                'nickname' => $nickname,
            ])
            ->andReturn($expectedData);

        $result = $this->repository->getPlayerByNickname($nickname);

        $this->assertIsArray($result);
        $this->assertEquals($expectedData, $result);
        $this->assertEquals('67890', $result['player_id']);
        $this->assertEquals($nickname, $result['nickname']);
    }

    public function test_get_player_by_nickname_throws_exception_when_connector_fails(): void
    {
        $nickname = 'NonExistentPlayer';

        $this->connector
            ->shouldReceive('get')
            ->once()
            ->with('players', [
                'nickname' => $nickname,
            ])
            ->andThrow(FaceITAPIConnectorException::notFound('Player not found'));

        $this->expectException(FaceITAPIConnectorException::class);
        $this->expectExceptionMessage('Player not found');

        $this->repository->getPlayerByNickname($nickname);
    }

    public function test_get_player_by_nickname_throws_authentication_exception(): void
    {
        $nickname = 'TestPlayer';

        $this->connector
            ->shouldReceive('get')
            ->once()
            ->with('players', [
                'nickname' => $nickname,
            ])
            ->andThrow(FaceITAPIConnectorException::authenticationError('Invalid API key'));

        $this->expectException(FaceITAPIConnectorException::class);
        $this->expectExceptionMessage('Invalid API key');

        $this->repository->getPlayerByNickname($nickname);
    }

    public function test_get_player_by_steam_id_handles_different_games(): void
    {
        $steamId = '76561198081165057';
        $game = 'csgo';
        $expectedData = [
            'player_id' => '12345',
            'nickname' => 'TestPlayer',
        ];

        $this->connector
            ->shouldReceive('get')
            ->once()
            ->with('players', [
                'game_player_id' => $steamId,
                'game' => $game,
            ])
            ->andReturn($expectedData);

        $result = $this->repository->getPlayerBySteamId($steamId, $game);

        $this->assertEquals($expectedData, $result);
    }

    public function test_get_player_match_history_returns_match_data(): void
    {
        $playerId = '12345';
        $expectedData = [
            'items' => [
                [
                    'match_id' => 'match-1',
                    'game' => 'cs2',
                    'status' => 'finished',
                ],
                [
                    'match_id' => 'match-2',
                    'game' => 'cs2',
                    'status' => 'finished',
                ],
            ],
            'start' => 0,
            'end' => 2,
        ];

        $this->connector
            ->shouldReceive('get')
            ->once()
            ->with("players/{$playerId}/history", [
                'offset' => 0,
                'limit' => 20,
            ])
            ->andReturn($expectedData);

        $result = $this->repository->getPlayerMatchHistory($playerId);

        $this->assertIsArray($result);
        $this->assertEquals($expectedData, $result);
        $this->assertArrayHasKey('items', $result);
    }

    public function test_get_player_match_history_with_game_filter(): void
    {
        $playerId = '12345';
        $game = 'cs2';
        $expectedData = [
            'items' => [],
            'start' => 0,
            'end' => 0,
        ];

        $this->connector
            ->shouldReceive('get')
            ->once()
            ->with("players/{$playerId}/history", [
                'offset' => 0,
                'limit' => 20,
                'game' => $game,
            ])
            ->andReturn($expectedData);

        $result = $this->repository->getPlayerMatchHistory($playerId, $game);

        $this->assertEquals($expectedData, $result);
    }

    public function test_get_player_match_history_with_offset_and_limit(): void
    {
        $playerId = '12345';
        $offset = 10;
        $limit = 50;
        $expectedData = [
            'items' => [],
            'start' => 10,
            'end' => 60,
        ];

        $this->connector
            ->shouldReceive('get')
            ->once()
            ->with("players/{$playerId}/history", [
                'offset' => $offset,
                'limit' => $limit,
            ])
            ->andReturn($expectedData);

        $result = $this->repository->getPlayerMatchHistory($playerId, null, $offset, $limit);

        $this->assertEquals($expectedData, $result);
    }

    public function test_get_player_match_history_with_all_parameters(): void
    {
        $playerId = '12345';
        $game = 'csgo';
        $offset = 5;
        $limit = 30;
        $expectedData = [
            'items' => [],
            'start' => 5,
            'end' => 35,
        ];

        $this->connector
            ->shouldReceive('get')
            ->once()
            ->with("players/{$playerId}/history", [
                'offset' => $offset,
                'limit' => $limit,
                'game' => $game,
            ])
            ->andReturn($expectedData);

        $result = $this->repository->getPlayerMatchHistory($playerId, $game, $offset, $limit);

        $this->assertEquals($expectedData, $result);
    }

    public function test_get_player_match_history_enforces_limit_bounds(): void
    {
        $playerId = '12345';
        $expectedData = [
            'items' => [],
            'start' => 0,
            'end' => 0,
        ];

        // Test limit too high (should be capped at 100)
        $this->connector
            ->shouldReceive('get')
            ->once()
            ->with("players/{$playerId}/history", [
                'offset' => 0,
                'limit' => 100,
            ])
            ->andReturn($expectedData);

        $result = $this->repository->getPlayerMatchHistory($playerId, null, 0, 200);
        $this->assertEquals($expectedData, $result);

        // Test limit too low (should be raised to 1)
        $this->connector
            ->shouldReceive('get')
            ->once()
            ->with("players/{$playerId}/history", [
                'offset' => 0,
                'limit' => 1,
            ])
            ->andReturn($expectedData);

        $result = $this->repository->getPlayerMatchHistory($playerId, null, 0, 0);
        $this->assertEquals($expectedData, $result);
    }

    public function test_get_player_match_history_enforces_offset_bounds(): void
    {
        $playerId = '12345';
        $expectedData = [
            'items' => [],
            'start' => 0,
            'end' => 0,
        ];

        // Test negative offset (should be raised to 0)
        $this->connector
            ->shouldReceive('get')
            ->once()
            ->with("players/{$playerId}/history", [
                'offset' => 0,
                'limit' => 20,
            ])
            ->andReturn($expectedData);

        $result = $this->repository->getPlayerMatchHistory($playerId, null, -5, 20);
        $this->assertEquals($expectedData, $result);
    }

    public function test_get_player_match_history_throws_exception_when_connector_fails(): void
    {
        $playerId = '12345';

        $this->connector
            ->shouldReceive('get')
            ->once()
            ->with("players/{$playerId}/history", \Mockery::type('array'))
            ->andThrow(FaceITAPIConnectorException::notFound('Player not found'));

        $this->expectException(FaceITAPIConnectorException::class);
        $this->expectExceptionMessage('Player not found');

        $this->repository->getPlayerMatchHistory($playerId);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
