<?php

namespace Tests\Unit\Services;

use App\Enums\MatchType;
use App\Enums\Team;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\PlayerMatchEvent;
use App\Models\User;
use App\Services\Matches\MatchDetailsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MatchDetailsServiceTest extends TestCase
{
    use RefreshDatabase;

    private MatchDetailsService $service;

    private User $user;

    private Player $player;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MatchDetailsService;

        $this->user = User::factory()->create([
            'steam_id' => '76561198012345678',
        ]);

        $this->player = Player::factory()->create([
            'steam_id' => '76561198012345678',
            'name' => 'TestPlayer',
        ]);
    }

    public function test_get_details_returns_empty_array_when_user_has_no_access()
    {
        // Create a match that the user doesn't have access to
        $match = GameMatch::factory()->create();

        $result = $this->service->getDetails($this->user, $match->id);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_get_details_returns_empty_array_when_match_not_found()
    {
        $result = $this->service->getDetails($this->user, 99999);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_get_details_returns_correct_structure_for_accessible_match()
    {
        $match = GameMatch::factory()->create([
            'map' => 'de_dust2',
            'winning_team' => 'A',
            'winning_team_score' => 16,
            'losing_team_score' => 14,
            'match_type' => MatchType::MATCHMAKING,
        ]);

        // Attach player to match so user has access
        $match->players()->attach($this->player->id, ['team' => 'A']);

        $result = $this->service->getDetails($this->user, $match->id);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('created_at', $result);
        $this->assertArrayHasKey('is_completed', $result);
        $this->assertArrayHasKey('match_details', $result);
        $this->assertArrayHasKey('player_stats', $result);
        $this->assertArrayHasKey('processing_status', $result);
        $this->assertArrayHasKey('progress_percentage', $result);
        $this->assertArrayHasKey('current_step', $result);
        $this->assertArrayHasKey('error_message', $result);

        $this->assertEquals($match->id, $result['id']);
        $this->assertTrue($result['is_completed']);
        $this->assertNull($result['processing_status']);
        $this->assertNull($result['progress_percentage']);
        $this->assertNull($result['current_step']);
        $this->assertNull($result['error_message']);
    }

    public function test_match_details_structure()
    {
        $match = GameMatch::factory()->create([
            'map' => 'de_mirage',
            'winning_team' => 'B',
            'winning_team_score' => 13,
            'losing_team_score' => 16,
            'match_type' => MatchType::FACEIT,
        ]);

        // Attach player to winning team
        $match->players()->attach($this->player->id, ['team' => 'B']);

        $result = $this->service->getDetails($this->user, $match->id);
        $matchDetails = $result['match_details'];

        $this->assertArrayHasKey('id', $matchDetails);
        $this->assertArrayHasKey('map', $matchDetails);
        $this->assertArrayHasKey('winning_team_score', $matchDetails);
        $this->assertArrayHasKey('losing_team_score', $matchDetails);
        $this->assertArrayHasKey('winning_team', $matchDetails);
        $this->assertArrayHasKey('player_won_match', $matchDetails);
        $this->assertArrayHasKey('player_was_participant', $matchDetails);
        $this->assertArrayHasKey('player_team', $matchDetails);
        $this->assertArrayHasKey('match_type', $matchDetails);
        $this->assertArrayHasKey('created_at', $matchDetails);

        $this->assertEquals($match->id, $matchDetails['id']);
        $this->assertEquals('de_mirage', $matchDetails['map']);
        $this->assertEquals(13, $matchDetails['winning_team_score']);
        $this->assertEquals(16, $matchDetails['losing_team_score']);
        $this->assertEquals('B', $matchDetails['winning_team']);
        $this->assertTrue($matchDetails['player_won_match']);
        $this->assertTrue($matchDetails['player_was_participant']);
        $this->assertEquals('B', $matchDetails['player_team']);
        $this->assertEquals(MatchType::FACEIT, $matchDetails['match_type']);
    }

    public function test_player_did_not_win_match()
    {
        $match = GameMatch::factory()->create([
            'winning_team' => 'A',
            'winning_team_score' => 16,
            'losing_team_score' => 14,
        ]);

        // Attach player to losing team
        $match->players()->attach($this->player->id, ['team' => 'B']);

        $result = $this->service->getDetails($this->user, $match->id);
        $matchDetails = $result['match_details'];

        $this->assertFalse($matchDetails['player_won_match']);
        $this->assertTrue($matchDetails['player_was_participant']);
        $this->assertEquals('B', $matchDetails['player_team']);
    }

    public function test_player_was_not_participant()
    {
        $match = GameMatch::factory()->create([
            'winning_team' => 'A',
            'winning_team_score' => 16,
            'losing_team_score' => 14,
        ]);

        // Don't attach player to match - they didn't participate

        $result = $this->service->getDetails($this->user, $match->id);

        // When player doesn't participate, service returns empty array
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_scoreboard_stats_structure()
    {
        $match = GameMatch::factory()->create();
        $match->players()->attach($this->player->id, ['team' => 'A']);

        // Create PlayerMatchEvent
        PlayerMatchEvent::factory()->create([
            'match_id' => $match->id,
            'player_steam_id' => $this->player->steam_id,
            'kills' => 20,
            'deaths' => 15,
            'first_kills' => 8,
            'first_deaths' => 6,
            'adr' => 85.5,
        ]);

        $result = $this->service->getDetails($this->user, $match->id);
        $playerStats = $result['player_stats'];

        $this->assertIsArray($playerStats);
        $this->assertCount(1, $playerStats);

        $playerStat = $playerStats[0];
        $this->assertArrayHasKey('player_kills', $playerStat);
        $this->assertArrayHasKey('player_deaths', $playerStat);
        $this->assertArrayHasKey('player_first_kill_differential', $playerStat);
        $this->assertArrayHasKey('player_kill_death_ratio', $playerStat);
        $this->assertArrayHasKey('player_adr', $playerStat);
        $this->assertArrayHasKey('team', $playerStat);
        $this->assertArrayHasKey('player_name', $playerStat);

        $this->assertEquals(20, $playerStat['player_kills']);
        $this->assertEquals(15, $playerStat['player_deaths']);
        $this->assertEquals(2, $playerStat['player_first_kill_differential']); // 8 - 6
        $this->assertEquals(1.33, $playerStat['player_kill_death_ratio']); // 20/15 rounded to 2 decimals
        $this->assertEquals(86, $playerStat['player_adr']); // 85.5 rounded
        $this->assertEquals('A', $playerStat['team']);
        $this->assertEquals('TestPlayer', $playerStat['player_name']);
    }

    public function test_kill_death_ratio_with_zero_deaths()
    {
        $match = GameMatch::factory()->create();
        $match->players()->attach($this->player->id, ['team' => 'A']);

        // Create PlayerMatchEvent with zero deaths
        PlayerMatchEvent::factory()->create([
            'match_id' => $match->id,
            'player_steam_id' => $this->player->steam_id,
            'kills' => 5,
            'deaths' => 0,
            'first_kills' => 2,
            'first_deaths' => 0,
            'adr' => 50.0,
        ]);

        $result = $this->service->getDetails($this->user, $match->id);
        $playerStat = $result['player_stats'][0];

        $this->assertEquals(5, $playerStat['player_kills']);
        $this->assertEquals(0, $playerStat['player_deaths']);
        $this->assertEquals(0.0, $playerStat['player_kill_death_ratio']); // Should handle division by zero
        $this->assertEquals(2, $playerStat['player_first_kill_differential']); // 2 - 0
    }

    public function test_multiple_players_in_scoreboard()
    {
        $match = GameMatch::factory()->create();

        $player2 = Player::factory()->create([
            'steam_id' => '76561198087654321',
            'name' => 'Player2',
        ]);

        $match->players()->attach($this->player->id, ['team' => 'A']);
        $match->players()->attach($player2->id, ['team' => 'B']);

        // Create PlayerMatchEvents for both players
        PlayerMatchEvent::factory()->create([
            'match_id' => $match->id,
            'player_steam_id' => $this->player->steam_id,
            'kills' => 20,
            'deaths' => 15,
            'first_kills' => 8,
            'first_deaths' => 6,
            'adr' => 85.5,
        ]);

        PlayerMatchEvent::factory()->create([
            'match_id' => $match->id,
            'player_steam_id' => $player2->steam_id,
            'kills' => 18,
            'deaths' => 12,
            'first_kills' => 6,
            'first_deaths' => 4,
            'adr' => 78.2,
        ]);

        $result = $this->service->getDetails($this->user, $match->id);
        $playerStats = $result['player_stats'];

        $this->assertCount(2, $playerStats);

        // Check that both players are included
        $playerNames = collect($playerStats)->pluck('player_name')->toArray();
        $this->assertContains('TestPlayer', $playerNames);
        $this->assertContains('Player2', $playerNames);

        // Check teams
        $teams = collect($playerStats)->pluck('team')->toArray();
        $this->assertContains('A', $teams);
        $this->assertContains('B', $teams);
    }

    public function test_caching_behavior()
    {
        $match = GameMatch::factory()->create();
        $match->players()->attach($this->player->id, ['team' => 'A']);

        // Mock the cache to verify caching is used
        Cache::shouldReceive('remember')
            ->once()
            ->andReturn([
                'id' => $match->id,
                'created_at' => $match->created_at,
                'is_completed' => true,
                'match_details' => [
                    'id' => $match->id,
                    'map' => 'de_dust2',
                    'winning_team_score' => 16,
                    'losing_team_score' => 14,
                    'winning_team' => 'A',
                    'player_won_match' => true,
                    'player_was_participant' => true,
                    'player_team' => 'A',
                    'match_type' => 'mm',
                    'created_at' => $match->created_at,
                ],
                'player_stats' => [],
                'processing_status' => null,
                'progress_percentage' => null,
                'current_step' => null,
                'error_message' => null,
            ]);

        $result = $this->service->getDetails($this->user, $match->id);

        $this->assertEquals($match->id, $result['id']);
        $this->assertTrue($result['is_completed']);
        $this->assertEquals('de_dust2', $result['match_details']['map']);
    }

    public function test_edge_case_with_missing_player_relationship()
    {
        $match = GameMatch::factory()->create();
        $match->players()->attach($this->player->id, ['team' => 'A']);

        // Create PlayerMatchEvent for the existing player (not a non-existent one)
        PlayerMatchEvent::factory()->create([
            'match_id' => $match->id,
            'player_steam_id' => $this->player->steam_id,
            'kills' => 10,
            'deaths' => 5,
            'first_kills' => 2,
            'first_deaths' => 1,
            'adr' => 50.0,
        ]);

        $result = $this->service->getDetails($this->user, $match->id);
        $playerStats = $result['player_stats'];

        // Should return the match details with player stats
        $this->assertIsArray($result);
        $this->assertArrayHasKey('match_details', $result);
        $this->assertIsArray($playerStats);
        $this->assertCount(1, $playerStats);
    }

    public function test_different_match_types()
    {
        $matchTypes = [
            MatchType::HLTV,
            MatchType::MATCHMAKING,
            MatchType::FACEIT,
            MatchType::ESPORTAL,
            MatchType::OTHER,
        ];

        foreach ($matchTypes as $matchType) {
            $match = GameMatch::factory()->create([
                'match_type' => $matchType,
            ]);
            $match->players()->attach($this->player->id, ['team' => 'A']);

            $result = $this->service->getDetails($this->user, $match->id);
            $matchDetails = $result['match_details'];

            $this->assertEquals($matchType, $matchDetails['match_type']);
        }
    }

    public function test_team_enum_values()
    {
        $teams = ['A', 'B'];

        foreach ($teams as $team) {
            $match = GameMatch::factory()->create([
                'winning_team' => $team,
            ]);
            $match->players()->attach($this->player->id, ['team' => $team]);

            $result = $this->service->getDetails($this->user, $match->id);
            $matchDetails = $result['match_details'];

            $this->assertEquals($team, $matchDetails['winning_team']);
            $this->assertEquals($team, $matchDetails['player_team']);
            $this->assertTrue($matchDetails['player_won_match']);
        }
    }
}
