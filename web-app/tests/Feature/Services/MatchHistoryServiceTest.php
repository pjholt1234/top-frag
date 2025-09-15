<?php

namespace Tests\Feature\Services;

use App\Enums\MatchType;
use App\Enums\Team;
use App\Models\GameMatch;
use App\Models\GunfightEvent;
use App\Models\Player;
use App\Models\PlayerMatchEvent;
use App\Models\User;
use App\Services\MatchHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MatchHistoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Player $player;

    private MatchHistoryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a user with a player
        $this->user = User::factory()->create([
            'steam_id' => 'STEAM_123456789',
        ]);

        $this->player = Player::factory()->create([
            'steam_id' => 'STEAM_123456789',
            'name' => 'TestPlayer',
        ]);

        $this->service = new MatchHistoryService(
            matchDetailsService: new \App\Services\Matches\MatchDetailsService
        );
    }

    #[Test]
    public function it_aggregates_match_data_for_user_with_matches()
    {
        // Create a match
        $match = GameMatch::factory()->create([
            'map' => 'de_dust2',
            'winning_team' => 'A',
            'winning_team_score' => 16,
            'losing_team_score' => 14,
            'match_type' => MatchType::MATCHMAKING,
            'total_rounds' => 30,
        ]);

        // Attach player to match
        $match->players()->attach($this->player->id, ['team' => 'A']);

        $result = $this->service->getPaginatedMatchHistory($this->user, 10, 1);

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertCount(1, $result['data']);
        $this->assertArrayHasKey('match_details', $result['data'][0]);
        $this->assertArrayHasKey('player_stats', $result['data'][0]);

        // Check match details
        $matchDetails = $result['data'][0]['match_details'];
        $this->assertEquals($match->id, $matchDetails['id']);
        $this->assertEquals('de_dust2', $matchDetails['map']);
        $this->assertEquals(16, $matchDetails['winning_team_score']);
        $this->assertEquals(14, $matchDetails['losing_team_score']);
        $this->assertEquals('A', $matchDetails['winning_team']);
        $this->assertTrue($matchDetails['player_won_match']);
        $this->assertEquals(MatchType::MATCHMAKING, $matchDetails['match_type']);
        $this->assertTrue($matchDetails['player_was_participant']);
    }

    #[Test]
    public function it_returns_empty_array_when_user_has_no_matches()
    {
        $result = $this->service->getPaginatedMatchHistory($this->user, 10, 1);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertEmpty($result['data']);
    }

    #[Test]
    public function it_correctly_identifies_player_lost_match()
    {
        $match = GameMatch::factory()->create([
            'winning_team' => 'A',
            'winning_team_score' => 16,
            'losing_team_score' => 14,
        ]);

        // Attach player to losing team
        $match->players()->attach($this->player->id, ['team' => 'B']);

        $result = $this->service->getPaginatedMatchHistory($this->user, 10, 1);
        $matchDetails = $result['data'][0]['match_details'];

        $this->assertFalse($matchDetails['player_won_match']);
    }

    #[Test]
    public function it_calculates_player_stats_correctly()
    {
        $match = GameMatch::factory()->create([
            'total_rounds' => 30,
        ]);

        // Attach player to match
        $match->players()->attach($this->player->id, ['team' => 'A']);

        // Create PlayerMatchEvent with the stats
        PlayerMatchEvent::factory()->create([
            'match_id' => $match->id,
            'player_steam_id' => $this->player->steam_id,
            'kills' => 2,
            'deaths' => 1,
            'first_kills' => 1,
            'first_deaths' => 1,
            'adr' => 5.0,
        ]);

        $result = $this->service->getPaginatedMatchHistory($this->user, 10, 1);
        $playerStats = $result['data'][0]['player_stats'][0];

        $this->assertEquals(2, $playerStats['player_kills']);
        $this->assertEquals(1, $playerStats['player_deaths']);
        $this->assertEquals(0, $playerStats['player_first_kill_differential']); // 1 first kill - 1 first death
        $this->assertEquals(2.0, $playerStats['player_kill_death_ratio']);
        $this->assertEquals(5.0, $playerStats['player_adr']);
        $this->assertEquals('A', $playerStats['team']);
        $this->assertEquals('TestPlayer', $playerStats['player_name']);
    }

    #[Test]
    public function it_handles_zero_deaths_in_kill_death_ratio()
    {
        $match = GameMatch::factory()->create([
            'total_rounds' => 30,
        ]);

        $match->players()->attach($this->player->id, ['team' => 'A']);

        // Create PlayerMatchEvent with zero deaths
        PlayerMatchEvent::factory()->create([
            'match_id' => $match->id,
            'player_steam_id' => $this->player->steam_id,
            'kills' => 1,
            'deaths' => 0,
            'first_kills' => 0,
            'first_deaths' => 0,
            'adr' => 0.0,
        ]);

        $result = $this->service->getPaginatedMatchHistory($this->user, 10, 1);
        $playerStats = $result['data'][0]['player_stats'][0];

        $this->assertEquals(1, $playerStats['player_kills']);
        $this->assertEquals(0, $playerStats['player_deaths']);
        $this->assertEquals(0.0, $playerStats['player_kill_death_ratio']); // 1 / 0 = 0 (handled by division by zero)
    }

    #[Test]
    public function it_calculates_average_damage_per_round_correctly()
    {
        $match = GameMatch::factory()->create([
            'total_rounds' => 20,
        ]);

        $match->players()->attach($this->player->id, ['team' => 'A']);

        // Create PlayerMatchEvent with specific ADR
        PlayerMatchEvent::factory()->create([
            'match_id' => $match->id,
            'player_steam_id' => $this->player->steam_id,
            'kills' => 0,
            'deaths' => 0,
            'first_kills' => 0,
            'first_deaths' => 0,
            'adr' => 7.5,
        ]);

        $result = $this->service->getPaginatedMatchHistory($this->user, 10, 1);
        $playerStats = $result['data'][0]['player_stats'][0];

        $this->assertEquals(8.0, $playerStats['player_adr']);
    }

    #[Test]
    public function it_handles_multiple_players_in_match()
    {
        $match = GameMatch::factory()->create([
            'total_rounds' => 30,
        ]);

        $otherPlayer = Player::factory()->create([
            'steam_id' => 'STEAM_987654321',
            'name' => 'OtherPlayer',
        ]);

        // Attach both players to match
        $match->players()->attach($this->player->id, ['team' => 'A']);
        $match->players()->attach($otherPlayer->id, ['team' => 'B']);

        // Create PlayerMatchEvent for both players
        PlayerMatchEvent::factory()->create([
            'match_id' => $match->id,
            'player_steam_id' => $this->player->steam_id,
            'kills' => 5,
            'deaths' => 3,
            'first_kills' => 2,
            'first_deaths' => 1,
            'adr' => 80.0,
        ]);

        PlayerMatchEvent::factory()->create([
            'match_id' => $match->id,
            'player_steam_id' => $otherPlayer->steam_id,
            'kills' => 4,
            'deaths' => 4,
            'first_kills' => 1,
            'first_deaths' => 2,
            'adr' => 70.0,
        ]);

        $result = $this->service->getPaginatedMatchHistory($this->user, 10, 1);
        $playerStats = $result['data'][0]['player_stats'];

        $this->assertCount(2, $playerStats);

        // Check that both players are included
        $playerNames = collect($playerStats)->pluck('player_name')->toArray();
        $this->assertContains('TestPlayer', $playerNames);
        $this->assertContains('OtherPlayer', $playerNames);
    }

    #[Test]
    public function it_handles_gunfight_events_correctly()
    {
        $match = GameMatch::factory()->create();

        // Create gunfight events where player is player_1
        GunfightEvent::factory()->create([
            'match_id' => $match->id,
            'player_1_steam_id' => $this->player->steam_id,
            'player_2_steam_id' => 'STEAM_987654321',
            'victor_steam_id' => $this->player->steam_id,
        ]);

        // Create gunfight events where player is player_2
        GunfightEvent::factory()->create([
            'match_id' => $match->id,
            'player_1_steam_id' => 'STEAM_987654321',
            'player_2_steam_id' => $this->player->steam_id,
            'victor_steam_id' => 'STEAM_987654321',
        ]);

        // Create gunfight event where player is not involved
        GunfightEvent::factory()->create([
            'match_id' => $match->id,
            'player_1_steam_id' => 'STEAM_111111111',
            'player_2_steam_id' => 'STEAM_222222222',
            'victor_steam_id' => 'STEAM_111111111',
        ]);

        // Attach player to match and get match history
        $match->players()->attach($this->player->id, ['team' => 'A']);
        $result = $this->service->getPaginatedMatchHistory($this->user, 10, 1);

        $this->assertCount(1, $result['data']);
        $this->assertArrayHasKey('match_details', $result['data'][0]);
    }

    #[Test]
    public function it_handles_user_without_player_relationship()
    {
        $userWithoutPlayer = User::factory()->create([
            'steam_id' => null,
        ]);

        $this->expectException(\App\Exceptions\PlayerNotFound::class);
        $this->service->getPaginatedMatchHistory($userWithoutPlayer, 10, 1);
    }

    #[Test]
    public function it_handles_match_with_no_gunfight_events()
    {
        $match = GameMatch::factory()->create([
            'total_rounds' => 30,
        ]);

        $match->players()->attach($this->player->id, ['team' => 'A']);

        // Create PlayerMatchEvent with zero stats
        PlayerMatchEvent::factory()->create([
            'match_id' => $match->id,
            'player_steam_id' => $this->player->steam_id,
            'kills' => 0,
            'deaths' => 0,
            'first_kills' => 0,
            'first_deaths' => 0,
            'adr' => 0.0,
        ]);

        $result = $this->service->getPaginatedMatchHistory($this->user, 10, 1);
        $playerStats = $result['data'][0]['player_stats'][0];

        $this->assertEquals(0, $playerStats['player_kills']);
        $this->assertEquals(0, $playerStats['player_deaths']);
        $this->assertEquals(0, $playerStats['player_first_kill_differential']);
        $this->assertEquals(0.0, $playerStats['player_kill_death_ratio']);
        $this->assertEquals(0.0, $playerStats['player_adr']);
    }

    #[Test]
    public function it_handles_match_with_no_damage_events()
    {
        $match = GameMatch::factory()->create([
            'total_rounds' => 30,
        ]);

        $match->players()->attach($this->player->id, ['team' => 'A']);

        // Create PlayerMatchEvent with kills but no damage
        PlayerMatchEvent::factory()->create([
            'match_id' => $match->id,
            'player_steam_id' => $this->player->steam_id,
            'kills' => 1,
            'deaths' => 0,
            'first_kills' => 0,
            'first_deaths' => 0,
            'adr' => 0.0,
        ]);

        $result = $this->service->getPaginatedMatchHistory($this->user, 10, 1);
        $playerStats = $result['data'][0]['player_stats'][0];

        $this->assertEquals(1, $playerStats['player_kills']);
        $this->assertEquals(0, $playerStats['player_deaths']);
        $this->assertEquals(0.0, $playerStats['player_adr']); // No damage events = 0 ADR
    }

    #[Test]
    public function it_correctly_identifies_first_kill_events()
    {
        $match = GameMatch::factory()->create([
            'total_rounds' => 30,
        ]);

        $match->players()->attach($this->player->id, ['team' => 'A']);

        // Create PlayerMatchEvent with first kill stats
        PlayerMatchEvent::factory()->create([
            'match_id' => $match->id,
            'player_steam_id' => $this->player->steam_id,
            'kills' => 2,
            'deaths' => 1,
            'first_kills' => 1,
            'first_deaths' => 1,
            'adr' => 0.0,
        ]);

        $result = $this->service->getPaginatedMatchHistory($this->user, 10, 1);
        $playerStats = $result['data'][0]['player_stats'][0];

        $this->assertEquals(2, $playerStats['player_kills']);
        $this->assertEquals(1, $playerStats['player_deaths']);
        $this->assertEquals(0, $playerStats['player_first_kill_differential']); // 1 first kill - 1 first death
    }

    #[Test]
    public function it_handles_multiple_matches_for_user()
    {
        // Create first match
        $match1 = GameMatch::factory()->create([
            'map' => 'de_dust2',
            'winning_team' => 'A',
            'winning_team_score' => 16,
            'losing_team_score' => 14,
        ]);

        // Create second match
        $match2 = GameMatch::factory()->create([
            'map' => 'de_mirage',
            'winning_team' => 'B',
            'winning_team_score' => 13,
            'losing_team_score' => 16,
        ]);

        // Attach player to both matches
        $match1->players()->attach($this->player->id, ['team' => 'A']);
        $match2->players()->attach($this->player->id, ['team' => 'A']);

        $result = $this->service->getPaginatedMatchHistory($this->user, 10, 1);

        $this->assertCount(2, $result['data']);

        // Check first match details
        $this->assertEquals('de_dust2', $result['data'][0]['match_details']['map']);
        $this->assertTrue($result['data'][0]['match_details']['player_won_match']);

        // Check second match details
        $this->assertEquals('de_mirage', $result['data'][1]['match_details']['map']);
        $this->assertFalse($result['data'][1]['match_details']['player_won_match']);
    }

    public function test_get_paginated_match_history_returns_correct_structure()
    {
        $user = User::factory()->create(['steam_id' => 'STEAM_0:1:123456789']);
        $player = Player::factory()->create(['steam_id' => $user->steam_id]);
        $match = GameMatch::factory()->create();

        $match->players()->attach($player->id, ['team' => 'A']);

        $result = $this->service->getPaginatedMatchHistory($user, 10, 1);

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertArrayHasKey('current_page', $result['pagination']);
        $this->assertArrayHasKey('per_page', $result['pagination']);
        $this->assertArrayHasKey('total', $result['pagination']);
        $this->assertArrayHasKey('last_page', $result['pagination']);
    }

    #[Test]
    public function it_correctly_identifies_player_did_not_participate_in_match()
    {
        // Create a match where the player is not participating
        $match = GameMatch::factory()->create([
            'map' => 'de_dust2',
            'winning_team' => 'A',
            'winning_team_score' => 16,
            'losing_team_score' => 14,
        ]);

        // Don't attach the player to the match - they didn't participate

        $result = $this->service->getPaginatedMatchHistory($this->user, 10, 1);

        // Since the player didn't participate, there should be no matches returned
        $this->assertArrayHasKey('data', $result);
        $this->assertEmpty($result['data']);
    }
}
