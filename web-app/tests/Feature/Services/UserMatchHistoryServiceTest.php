<?php

namespace Tests\Feature\Services;

use App\Enums\MatchType;
use App\Enums\Team;
use App\Models\DamageEvent;
use App\Models\GameMatch;
use App\Models\GunfightEvent;
use App\Models\Player;
use App\Models\User;
use App\Services\UserMatchHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserMatchHistoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Player $player;

    private UserMatchHistoryService $service;

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

        $this->service = new UserMatchHistoryService;
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

        $result = $this->service->aggregateMatchData($this->user);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('match_details', $result[0]);
        $this->assertArrayHasKey('player_stats', $result[0]);

        // Check match details
        $matchDetails = $result[0]['match_details'];
        $this->assertEquals($match->id, $matchDetails['match_id']);
        $this->assertEquals('de_dust2', $matchDetails['map']);
        $this->assertEquals(16, $matchDetails['winning_team_score']);
        $this->assertEquals(14, $matchDetails['losing_team_score']);
        $this->assertEquals('A', $matchDetails['winning_team_name']);
        $this->assertTrue($matchDetails['player_won_match']);
        $this->assertEquals(MatchType::MATCHMAKING, $matchDetails['match_type']);
        $this->assertTrue($matchDetails['player_was_participant']);
    }

    #[Test]
    public function it_returns_empty_array_when_user_has_no_matches()
    {
        $result = $this->service->aggregateMatchData($this->user);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
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

        $result = $this->service->aggregateMatchData($this->user);
        $matchDetails = $result[0]['match_details'];

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

        // Create gunfight events
        GunfightEvent::factory()->create([
            'match_id' => $match->id,
            'player_1_steam_id' => $this->player->steam_id,
            'player_2_steam_id' => 'STEAM_987654321',
            'victor_steam_id' => $this->player->steam_id,
            'is_first_kill' => true,
        ]);

        GunfightEvent::factory()->create([
            'match_id' => $match->id,
            'player_1_steam_id' => $this->player->steam_id,
            'player_2_steam_id' => 'STEAM_987654321',
            'victor_steam_id' => $this->player->steam_id,
            'is_first_kill' => false,
        ]);

        // Create death event
        GunfightEvent::factory()->create([
            'match_id' => $match->id,
            'player_1_steam_id' => 'STEAM_987654321',
            'player_2_steam_id' => $this->player->steam_id,
            'victor_steam_id' => 'STEAM_987654321',
            'is_first_kill' => true,
        ]);

        // Create damage events
        DamageEvent::factory()->create([
            'match_id' => $match->id,
            'attacker_steam_id' => $this->player->steam_id,
            'health_damage' => 100,
        ]);

        DamageEvent::factory()->create([
            'match_id' => $match->id,
            'attacker_steam_id' => $this->player->steam_id,
            'health_damage' => 50,
        ]);

        $result = $this->service->aggregateMatchData($this->user);
        $playerStats = $result[0]['player_stats'][0];

        $this->assertEquals(2, $playerStats['player_kills']);
        $this->assertEquals(1, $playerStats['player_deaths']);
        $this->assertEquals(0, $playerStats['player_first_kill_differential']); // 1 first kill - 1 first death
        $this->assertEquals(2.0, $playerStats['player_kill_death_ratio']);
        $this->assertEquals(5.0, $playerStats['player_adr']); // (100 + 50) / 30 rounds
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

        // Create only kill events
        GunfightEvent::factory()->create([
            'match_id' => $match->id,
            'player_1_steam_id' => $this->player->steam_id,
            'player_2_steam_id' => 'STEAM_987654321',
            'victor_steam_id' => $this->player->steam_id,
        ]);

        $result = $this->service->aggregateMatchData($this->user);
        $playerStats = $result[0]['player_stats'][0];

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

        // Create damage events
        DamageEvent::factory()->create([
            'match_id' => $match->id,
            'attacker_steam_id' => $this->player->steam_id,
            'health_damage' => 100,
        ]);

        DamageEvent::factory()->create([
            'match_id' => $match->id,
            'attacker_steam_id' => $this->player->steam_id,
            'health_damage' => 50,
        ]);

        $result = $this->service->aggregateMatchData($this->user);
        $playerStats = $result[0]['player_stats'][0];

        $this->assertEquals(7.5, $playerStats['player_adr']); // (100 + 50) / 20 rounds
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

        $result = $this->service->aggregateMatchData($this->user);
        $playerStats = $result[0]['player_stats'];

        $this->assertCount(2, $playerStats);

        // Check that both players are included
        $playerNames = collect($playerStats)->pluck('player_name')->toArray();
        $this->assertContains('TestPlayer', $playerNames);
        $this->assertContains('OtherPlayer', $playerNames);
    }

    #[Test]
    public function it_gets_all_player_gunfight_events_correctly()
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

        $events = $this->service->getAllPlayerGunfightEvents($match, $this->player);

        $this->assertCount(2, $events);
        $this->assertTrue($events->every(function ($event) {
            return $event->player_1_steam_id === $this->player->steam_id ||
                $event->player_2_steam_id === $this->player->steam_id;
        }));
    }

    #[Test]
    public function it_handles_user_without_player_relationship()
    {
        $userWithoutPlayer = User::factory()->create([
            'steam_id' => null,
        ]);

        $result = $this->service->aggregateMatchData($userWithoutPlayer);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function it_handles_match_with_no_gunfight_events()
    {
        $match = GameMatch::factory()->create([
            'total_rounds' => 30,
        ]);

        $match->players()->attach($this->player->id, ['team' => 'A']);

        $result = $this->service->aggregateMatchData($this->user);
        $playerStats = $result[0]['player_stats'][0];

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

        // Only create gunfight events, no damage events
        GunfightEvent::factory()->create([
            'match_id' => $match->id,
            'player_1_steam_id' => $this->player->steam_id,
            'player_2_steam_id' => 'STEAM_987654321',
            'victor_steam_id' => $this->player->steam_id,
        ]);

        $result = $this->service->aggregateMatchData($this->user);
        $playerStats = $result[0]['player_stats'][0];

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

        // Create first kill event
        GunfightEvent::factory()->create([
            'match_id' => $match->id,
            'player_1_steam_id' => $this->player->steam_id,
            'player_2_steam_id' => 'STEAM_987654321',
            'victor_steam_id' => $this->player->steam_id,
            'is_first_kill' => true,
        ]);

        // Create regular kill event
        GunfightEvent::factory()->create([
            'match_id' => $match->id,
            'player_1_steam_id' => $this->player->steam_id,
            'player_2_steam_id' => 'STEAM_987654321',
            'victor_steam_id' => $this->player->steam_id,
            'is_first_kill' => false,
        ]);

        // Create first death event
        GunfightEvent::factory()->create([
            'match_id' => $match->id,
            'player_1_steam_id' => 'STEAM_987654321',
            'player_2_steam_id' => $this->player->steam_id,
            'victor_steam_id' => 'STEAM_987654321',
            'is_first_kill' => true,
        ]);

        $result = $this->service->aggregateMatchData($this->user);
        $playerStats = $result[0]['player_stats'][0];

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

        $result = $this->service->aggregateMatchData($this->user);

        $this->assertCount(2, $result);

        // Check first match details
        $this->assertEquals('de_dust2', $result[0]['match_details']['map']);
        $this->assertTrue($result[0]['match_details']['player_won_match']);

        // Check second match details
        $this->assertEquals('de_mirage', $result[1]['match_details']['map']);
        $this->assertFalse($result[1]['match_details']['player_won_match']);
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

    public function test_get_recent_match_history_returns_correct_number_of_matches()
    {
        $user = User::factory()->create(['steam_id' => 'STEAM_0:1:123456789']);
        $player = Player::factory()->create(['steam_id' => $user->steam_id]);

        // Create 10 matches
        for ($i = 0; $i < 10; $i++) {
            $match = GameMatch::factory()->create();
            $match->players()->attach($player->id, ['team' => 'A']);
        }

        $result = $this->service->getRecentMatchHistory($user, 5);

        $this->assertCount(5, $result);
    }

    public function test_optimized_methods_produce_same_results_as_original()
    {
        $user = User::factory()->create(['steam_id' => 'STEAM_0:1:123456789']);
        $player = Player::factory()->create(['steam_id' => $user->steam_id]);
        $match = GameMatch::factory()->create(['total_rounds' => 30]);

        $match->players()->attach($player->id, ['team' => 'A']);

        // Create some gunfight events
        GunfightEvent::factory()->count(5)->create([
            'match_id' => $match->id,
            'victor_steam_id' => $player->steam_id,
            'is_first_kill' => true,
        ]);

        GunfightEvent::factory()->count(3)->create([
            'match_id' => $match->id,
            'player_1_steam_id' => $player->steam_id,
            'victor_steam_id' => 'STEAM_0:1:999999999', // Different player wins
        ]);

        // Create some damage events
        DamageEvent::factory()->count(10)->create([
            'match_id' => $match->id,
            'attacker_steam_id' => $player->steam_id,
            'health_damage' => 50,
        ]);

        $originalResult = $this->service->aggregateMatchData($user);
        $optimizedResult = $this->service->getPaginatedMatchHistory($user, 10, 1);

        // Compare the first match data
        $this->assertEquals(
            $originalResult[0]['match_details'],
            $optimizedResult['data'][0]['match_details']
        );

        // Compare player stats (should be identical)
        $this->assertEquals(
            $originalResult[0]['player_stats'][0]['player_kills'],
            $optimizedResult['data'][0]['player_stats'][0]['player_kills']
        );
    }
}
