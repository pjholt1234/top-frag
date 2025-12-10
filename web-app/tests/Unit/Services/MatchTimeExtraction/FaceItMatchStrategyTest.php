<?php

namespace Tests\Unit\Services\MatchTimeExtraction;

use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\Player;
use App\Models\PlayerRank;
use App\Services\FaceITRepository;
use App\Services\MatchTimeExtraction\FaceItMatchStrategy;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class FaceItMatchStrategyTest extends TestCase
{
    use RefreshDatabase;

    private FaceITRepository $faceITRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->faceITRepository = Mockery::mock(FaceITRepository::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_extract_returns_null_when_filename_is_empty(): void
    {
        $strategy = new FaceItMatchStrategy($this->faceITRepository);
        $gameMatch = GameMatch::factory()->create();

        $result = $strategy->extract(null, $gameMatch);

        $this->assertNull($result);
    }

    public function test_extract_returns_null_when_filename_does_not_match_pattern(): void
    {
        $strategy = new FaceItMatchStrategy($this->faceITRepository);
        $gameMatch = GameMatch::factory()->create();

        $result = $strategy->extract('invalid-filename.dem', $gameMatch);

        $this->assertNull($result);
    }

    public function test_extract_returns_null_when_match_validation_fails(): void
    {
        $strategy = new FaceItMatchStrategy($this->faceITRepository);
        $gameMatch = GameMatch::factory()->create([
            'map' => 'de_dust2',
            'winning_team_score' => 16,
            'losing_team_score' => 14,
        ]);

        $this->faceITRepository
            ->shouldReceive('getMatchDetails')
            ->once()
            ->andReturn([
                'started_at' => 1760394042,
                'teams' => [],
            ]);

        $this->faceITRepository
            ->shouldReceive('getMatchStats')
            ->once()
            ->andReturn([
                'rounds' => [
                    [
                        'round_stats' => [
                            'Map' => 'de_mirage', // Different map
                            'Score' => '16 / 14',
                        ],
                    ],
                ],
            ]);

        Log::shouldReceive('channel')
            ->with('parser')
            ->andReturnSelf();
        Log::shouldReceive('warning')
            ->once();

        $result = $strategy->extract('1-25e72cdb-ac23-4237-a95d-701603b58681-1-1.dem', $gameMatch);

        $this->assertNull($result);
    }

    public function test_extract_returns_carbon_instance_when_successful(): void
    {
        $strategy = new FaceItMatchStrategy($this->faceITRepository);
        $gameMatch = GameMatch::factory()->create([
            'map' => 'de_dust2',
            'winning_team_score' => 16,
            'losing_team_score' => 14,
        ]);

        // Create 10 players for the match with unique steam_ids
        $players = Player::factory()->count(10)->create();
        foreach ($players as $player) {
            MatchPlayer::factory()->create([
                'match_id' => $gameMatch->id,
                'player_id' => $player->id,
            ]);
        }

        $matchId = '1-25e72cdb-ac23-4237-a95d-701603b58681';
        $startedAt = 1760394042;

        // Create roster with player_ids matching the players
        $roster1 = [];
        $roster2 = [];
        $faceitIds = [];
        foreach ($players->take(5) as $index => $player) {
            $faceitId = 'faceit-'.$player->id;
            $faceitIds[] = $faceitId;
            $roster1[] = ['player_id' => $faceitId];
        }
        foreach ($players->skip(5) as $index => $player) {
            $faceitId = 'faceit-'.$player->id;
            $faceitIds[] = $faceitId;
            $roster2[] = ['player_id' => $faceitId];
        }

        $this->faceITRepository
            ->shouldReceive('getMatchDetails')
            ->once()
            ->with($matchId)
            ->andReturn([
                'started_at' => $startedAt,
                'teams' => [
                    [
                        'roster' => $roster1,
                    ],
                    [
                        'roster' => $roster2,
                    ],
                ],
            ]);

        $this->faceITRepository
            ->shouldReceive('getMatchStats')
            ->once()
            ->with($matchId)
            ->andReturn([
                'rounds' => [
                    [
                        'round_stats' => [
                            'Map' => 'de_dust2',
                            'Score' => '16 / 14',
                        ],
                    ],
                ],
            ]);

        // Mock player details for each player - match by faceit ID
        foreach ($players as $index => $player) {
            $faceitId = $faceitIds[$index];
            $this->faceITRepository
                ->shouldReceive('getPlayerByFaceITID')
                ->with($faceitId)
                ->once()
                ->andReturn([
                    'games' => [
                        'cs2' => [
                            'game_player_id' => $player->steam_id,
                            'faceit_elo' => 2500,
                            'skill_level' => 8,
                        ],
                    ],
                ]);
        }

        $result = $strategy->extract('1-25e72cdb-ac23-4237-a95d-701603b58681-1-1.dem', $gameMatch);

        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertEquals($startedAt, $result->timestamp);
    }

    public function test_extract_updates_player_faceit_id(): void
    {
        $strategy = new FaceItMatchStrategy($this->faceITRepository);
        $gameMatch = GameMatch::factory()->create([
            'map' => 'de_dust2',
            'winning_team_score' => 16,
            'losing_team_score' => 14,
        ]);

        $player = Player::factory()->create([
            'steam_id' => '76561198081165057',
            'faceit_id' => null,
        ]);
        MatchPlayer::factory()->create([
            'match_id' => $gameMatch->id,
            'player_id' => $player->id,
        ]);

        // Create 9 more players
        $otherPlayers = Player::factory()->count(9)->create();
        foreach ($otherPlayers as $otherPlayer) {
            MatchPlayer::factory()->create([
                'match_id' => $gameMatch->id,
                'player_id' => $otherPlayer->id,
            ]);
        }

        $matchId = '1-25e72cdb-ac23-4237-a95d-701603b58681';
        $faceitId = 'd69085ab-a7e3-4959-bad6-d965fed35553';

        // Create roster arrays properly
        $roster1 = [['player_id' => $faceitId]];
        foreach ($otherPlayers->take(4) as $otherPlayer) {
            $roster1[] = ['player_id' => 'faceit-'.$otherPlayer->id];
        }
        $roster2 = [];
        foreach ($otherPlayers->skip(4) as $otherPlayer) {
            $roster2[] = ['player_id' => 'faceit-'.$otherPlayer->id];
        }

        $this->faceITRepository
            ->shouldReceive('getMatchDetails')
            ->once()
            ->andReturn([
                'started_at' => 1760394042,
                'teams' => [
                    [
                        'roster' => $roster1,
                    ],
                    [
                        'roster' => $roster2,
                    ],
                ],
            ]);

        $this->faceITRepository
            ->shouldReceive('getMatchStats')
            ->once()
            ->andReturn([
                'rounds' => [
                    [
                        'round_stats' => [
                            'Map' => 'de_dust2',
                            'Score' => '16 / 14',
                        ],
                    ],
                ],
            ]);

        // Mock player details - first for the main player
        $this->faceITRepository
            ->shouldReceive('getPlayerByFaceITID')
            ->with($faceitId)
            ->once()
            ->andReturn([
                'games' => [
                    'cs2' => [
                        'game_player_id' => $player->steam_id,
                        'faceit_elo' => 2500,
                        'skill_level' => 8,
                    ],
                ],
            ]);

        // Then for the other players
        foreach ($otherPlayers->take(4) as $otherPlayer) {
            $this->faceITRepository
                ->shouldReceive('getPlayerByFaceITID')
                ->with('faceit-'.$otherPlayer->id)
                ->once()
                ->andReturn([
                    'games' => [
                        'cs2' => [
                            'game_player_id' => $otherPlayer->steam_id,
                            'faceit_elo' => 2500,
                            'skill_level' => 8,
                        ],
                    ],
                ]);
        }
        foreach ($otherPlayers->skip(4) as $otherPlayer) {
            $this->faceITRepository
                ->shouldReceive('getPlayerByFaceITID')
                ->with('faceit-'.$otherPlayer->id)
                ->once()
                ->andReturn([
                    'games' => [
                        'cs2' => [
                            'game_player_id' => $otherPlayer->steam_id,
                            'faceit_elo' => 2500,
                            'skill_level' => 8,
                        ],
                    ],
                ]);
        }

        $strategy->extract('1-25e72cdb-ac23-4237-a95d-701603b58681-1-1.dem', $gameMatch);

        $player->refresh();
        $this->assertEquals($faceitId, $player->faceit_id);
    }

    public function test_extract_creates_player_rank_when_elo_differs(): void
    {
        $strategy = new FaceItMatchStrategy($this->faceITRepository);
        $gameMatch = GameMatch::factory()->create([
            'map' => 'de_dust2',
            'winning_team_score' => 16,
            'losing_team_score' => 14,
        ]);

        $player = Player::factory()->create([
            'steam_id' => '76561198081165057',
        ]);
        MatchPlayer::factory()->create([
            'match_id' => $gameMatch->id,
            'player_id' => $player->id,
        ]);

        // Create existing rank with different elo
        PlayerRank::factory()->create([
            'player_id' => $player->id,
            'rank_type' => 'faceit',
            'rank_value' => 2400,
        ]);

        // Create 9 more players
        $otherPlayers = Player::factory()->count(9)->create();
        foreach ($otherPlayers as $otherPlayer) {
            MatchPlayer::factory()->create([
                'match_id' => $gameMatch->id,
                'player_id' => $otherPlayer->id,
            ]);
        }

        $matchId = '1-25e72cdb-ac23-4237-a95d-701603b58681';
        $faceitId = 'd69085ab-a7e3-4959-bad6-d965fed35553';
        $newElo = 2500;

        // Create roster arrays properly
        $roster1 = [['player_id' => $faceitId]];
        foreach ($otherPlayers->take(4) as $otherPlayer) {
            $roster1[] = ['player_id' => 'faceit-'.$otherPlayer->id];
        }
        $roster2 = [];
        foreach ($otherPlayers->skip(4) as $otherPlayer) {
            $roster2[] = ['player_id' => 'faceit-'.$otherPlayer->id];
        }

        $this->faceITRepository
            ->shouldReceive('getMatchDetails')
            ->once()
            ->andReturn([
                'started_at' => 1760394042,
                'teams' => [
                    [
                        'roster' => $roster1,
                    ],
                    [
                        'roster' => $roster2,
                    ],
                ],
            ]);

        $this->faceITRepository
            ->shouldReceive('getMatchStats')
            ->once()
            ->andReturn([
                'rounds' => [
                    [
                        'round_stats' => [
                            'Map' => 'de_dust2',
                            'Score' => '16 / 14',
                        ],
                    ],
                ],
            ]);

        // Mock player details - first for the main player
        $this->faceITRepository
            ->shouldReceive('getPlayerByFaceITID')
            ->with($faceitId)
            ->once()
            ->andReturn([
                'games' => [
                    'cs2' => [
                        'game_player_id' => $player->steam_id,
                        'faceit_elo' => $newElo,
                        'skill_level' => 8,
                    ],
                ],
            ]);

        // Then for the other players
        foreach ($otherPlayers->take(4) as $otherPlayer) {
            $this->faceITRepository
                ->shouldReceive('getPlayerByFaceITID')
                ->with('faceit-'.$otherPlayer->id)
                ->once()
                ->andReturn([
                    'games' => [
                        'cs2' => [
                            'game_player_id' => $otherPlayer->steam_id,
                            'faceit_elo' => 2500,
                            'skill_level' => 8,
                        ],
                    ],
                ]);
        }
        foreach ($otherPlayers->skip(4) as $otherPlayer) {
            $this->faceITRepository
                ->shouldReceive('getPlayerByFaceITID')
                ->with('faceit-'.$otherPlayer->id)
                ->once()
                ->andReturn([
                    'games' => [
                        'cs2' => [
                            'game_player_id' => $otherPlayer->steam_id,
                            'faceit_elo' => 2500,
                            'skill_level' => 8,
                        ],
                    ],
                ]);
        }

        $strategy->extract('1-25e72cdb-ac23-4237-a95d-701603b58681-1-1.dem', $gameMatch);

        $newRank = PlayerRank::where('player_id', $player->id)
            ->where('rank_type', 'faceit')
            ->where('rank_value', $newElo)
            ->first();

        $this->assertNotNull($newRank);
        $this->assertEquals($newElo, $newRank->rank_value);
    }

    private function createFaceitRoster($players): array
    {
        $roster = [];
        foreach ($players as $player) {
            $roster[] = [
                'player_id' => 'faceit-'.$player->id,
            ];
        }

        return $roster;
    }
}
