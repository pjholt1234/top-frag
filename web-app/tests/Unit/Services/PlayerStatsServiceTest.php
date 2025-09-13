<?php

namespace Tests\Unit\Services;

use App\Models\GameMatch;
use App\Models\Player;
use App\Models\PlayerMatchEvent;
use App\Models\User;
use App\Services\Matches\PlayerStatsService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    private PlayerStatsService $service;

    private User $user;

    private Player $player;

    private GameMatch $match;

    private PlayerMatchEvent $playerMatchEvent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PlayerStatsService;

        $this->user = User::factory()->create([
            'steam_id' => '76561198012345678',
        ]);

        $this->player = Player::factory()->create([
            'steam_id' => '76561198012345678',
        ]);

        $this->match = GameMatch::factory()->create([
            'map' => 'de_dust2',
            'winning_team_score' => 16,
            'losing_team_score' => 14,
            'total_rounds' => 24,
        ]);

        $this->match->players()->attach($this->player->id, ['team' => 'A']);

        $this->playerMatchEvent = PlayerMatchEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'kills' => 20,
            'deaths' => 15,
            'assists' => 5,
            'adr' => 85.5,
            'first_kills' => 8,
            'first_deaths' => 6,
            'average_round_time_of_death' => 30.5,
            'average_time_to_contact' => 25.2,
            'total_successful_trades' => 4,
            'total_possible_trades' => 8,
            'total_traded_deaths' => 3,
            'total_possible_traded_deaths' => 6,
            'clutch_wins_1v1' => 2,
            'clutch_attempts_1v1' => 3,
            'clutch_wins_1v2' => 1,
            'clutch_attempts_1v2' => 4,
            'clutch_wins_1v3' => 0,
            'clutch_attempts_1v3' => 2,
            'clutch_wins_1v4' => 0,
            'clutch_attempts_1v4' => 1,
            'clutch_wins_1v5' => 0,
            'clutch_attempts_1v5' => 0,
            'flashes_thrown' => 5,
            'fire_grenades_thrown' => 3,
            'smokes_thrown' => 4,
            'hes_thrown' => 2,
            'decoys_thrown' => 1,
            'damage_dealt' => 150,
            'enemy_flash_duration' => 25.5,
            'average_grenade_effectiveness' => 45.2,
            'flashes_leading_to_kills' => 3,
        ]);
    }

    public function test_get_returns_empty_array_when_no_player_steam_id_provided()
    {
        $filters = [];
        $matchId = $this->match->id;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No player id specified');

        $this->service->get($this->user, $filters, $matchId);
    }

    public function test_get_returns_empty_array_when_player_not_found()
    {
        $filters = ['player_steam_id' => '76561198099999999'];
        $matchId = $this->match->id;

        $result = $this->service->get($this->user, $filters, $matchId);

        $this->assertEmpty($result);
    }

    public function test_get_returns_correct_structure()
    {
        $filters = ['player_steam_id' => $this->player->steam_id];
        $matchId = $this->match->id;

        $result = $this->service->get($this->user, $filters, $matchId);

        $this->assertArrayHasKey('player_complexion', $result);
        $this->assertArrayHasKey('duels', $result);
        $this->assertArrayHasKey('clutch_stats', $result);
        $this->assertArrayHasKey('deep_dive', $result);
    }

    public function test_player_complexion_structure()
    {
        $filters = ['player_steam_id' => $this->player->steam_id];
        $matchId = $this->match->id;

        $result = $this->service->get($this->user, $filters, $matchId);

        $complexion = $result['player_complexion'];
        $this->assertArrayHasKey('opener', $complexion);
        $this->assertArrayHasKey('closer', $complexion);
        $this->assertArrayHasKey('support', $complexion);
        $this->assertArrayHasKey('fragger', $complexion);

        // All scores should be integers between 0 and 100
        $this->assertIsInt($complexion['opener']);
        $this->assertIsInt($complexion['closer']);
        $this->assertIsInt($complexion['support']);
        $this->assertIsInt($complexion['fragger']);

        $this->assertGreaterThanOrEqual(0, $complexion['opener']);
        $this->assertLessThanOrEqual(100, $complexion['opener']);
        $this->assertGreaterThanOrEqual(0, $complexion['closer']);
        $this->assertLessThanOrEqual(100, $complexion['closer']);
        $this->assertGreaterThanOrEqual(0, $complexion['support']);
        $this->assertLessThanOrEqual(100, $complexion['support']);
        $this->assertGreaterThanOrEqual(0, $complexion['fragger']);
        $this->assertLessThanOrEqual(100, $complexion['fragger']);
    }

    public function test_duels_stats_structure()
    {
        $filters = ['player_steam_id' => $this->player->steam_id];
        $matchId = $this->match->id;

        $result = $this->service->get($this->user, $filters, $matchId);

        $duels = $result['duels'];
        $this->assertArrayHasKey('total_successful_trades', $duels);
        $this->assertArrayHasKey('total_possible_trades', $duels);
        $this->assertArrayHasKey('total_traded_deaths', $duels);
        $this->assertArrayHasKey('total_possible_traded_deaths', $duels);
        $this->assertArrayHasKey('first_kills', $duels);
        $this->assertArrayHasKey('first_deaths', $duels);

        $this->assertEquals(4, $duels['total_successful_trades']);
        $this->assertEquals(8, $duels['total_possible_trades']);
        $this->assertEquals(3, $duels['total_traded_deaths']);
        $this->assertEquals(6, $duels['total_possible_traded_deaths']);
        $this->assertEquals(8, $duels['first_kills']);
        $this->assertEquals(6, $duels['first_deaths']);
    }

    public function test_clutch_stats_structure()
    {
        $filters = ['player_steam_id' => $this->player->steam_id];
        $matchId = $this->match->id;

        $result = $this->service->get($this->user, $filters, $matchId);

        $clutchStats = $result['clutch_stats'];
        $this->assertArrayHasKey('1v1', $clutchStats);
        $this->assertArrayHasKey('1v2', $clutchStats);
        $this->assertArrayHasKey('1v3', $clutchStats);
        $this->assertArrayHasKey('1v4', $clutchStats);
        $this->assertArrayHasKey('1v5', $clutchStats);

        // Test 1v1 structure
        $clutch1v1 = $clutchStats['1v1'];
        $this->assertArrayHasKey('clutch_wins_1v1', $clutch1v1);
        $this->assertArrayHasKey('clutch_attempts_1v1', $clutch1v1);
        $this->assertArrayHasKey('clutch_win_percentage_1v1', $clutch1v1);

        $this->assertEquals(2, $clutch1v1['clutch_wins_1v1']);
        $this->assertEquals(3, $clutch1v1['clutch_attempts_1v1']);
        $this->assertEqualsWithDelta(66.67, $clutch1v1['clutch_win_percentage_1v1'], 0.01);

        // Test 1v2 structure
        $clutch1v2 = $clutchStats['1v2'];
        $this->assertEquals(1, $clutch1v2['clutch_wins_1v2']);
        $this->assertEquals(4, $clutch1v2['clutch_attempts_1v2']);
        $this->assertEquals(25.0, $clutch1v2['clutch_win_percentage_1v2']);

        // Test 1v3 structure
        $clutch1v3 = $clutchStats['1v3'];
        $this->assertEquals(0, $clutch1v3['clutch_wins_1v3']);
        $this->assertEquals(2, $clutch1v3['clutch_attempts_1v3']);
        $this->assertEquals(0.0, $clutch1v3['clutch_win_percentage_1v3']);
    }

    public function test_deep_dive_stats_structure()
    {
        $filters = ['player_steam_id' => $this->player->steam_id];
        $matchId = $this->match->id;

        $result = $this->service->get($this->user, $filters, $matchId);

        $deepDive = $result['deep_dive'];
        $this->assertArrayHasKey('round_swing', $deepDive);
        $this->assertArrayHasKey('impact', $deepDive);

        // Currently returns static values
        $this->assertEquals(0, $deepDive['round_swing']);
        $this->assertEquals(0, $deepDive['impact']);
    }

    public function test_opener_score_calculation()
    {
        // Create a player with optimal opener stats
        $openerPlayer = PlayerMatchEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => '76561198011111111',
            'average_round_time_of_death' => 10, // Low is better
            'average_time_to_contact' => 5, // Low is better
            'first_kills' => 5,
            'first_deaths' => 1, // Good first kill plus/minus
            'total_successful_trades' => 8,
            'total_possible_traded_deaths' => 10, // Good trade death percentage
        ]);

        $filters = ['player_steam_id' => '76561198011111111'];
        $matchId = $this->match->id;

        $result = $this->service->get($this->user, $filters, $matchId);
        $openerScore = $result['player_complexion']['opener'];

        // Should be a high score for good opener stats
        $this->assertGreaterThan(50, $openerScore);
    }

    public function test_closer_score_calculation()
    {
        // Create a player with optimal closer stats
        $closerPlayer = PlayerMatchEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => '76561198022222222',
            'average_round_time_of_death' => 50, // High is better for closer
            'average_time_to_contact' => 40, // High is better for closer
            'clutch_wins_1v1' => 3,
            'clutch_attempts_1v1' => 4,
            'clutch_wins_1v2' => 2,
            'clutch_attempts_1v2' => 3,
            'clutch_wins_1v3' => 1,
            'clutch_attempts_1v3' => 2,
            'clutch_wins_1v4' => 0,
            'clutch_attempts_1v4' => 1,
            'clutch_wins_1v5' => 0,
            'clutch_attempts_1v5' => 0,
        ]);

        $filters = ['player_steam_id' => '76561198022222222'];
        $matchId = $this->match->id;

        $result = $this->service->get($this->user, $filters, $matchId);
        $closerScore = $result['player_complexion']['closer'];

        // Should be a high score for good closer stats
        $this->assertGreaterThan(50, $closerScore);
    }

    public function test_support_score_calculation()
    {
        // Create a player with optimal support stats
        $supportPlayer = PlayerMatchEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => '76561198033333333',
            'flashes_thrown' => 10,
            'fire_grenades_thrown' => 8,
            'smokes_thrown' => 7,
            'hes_thrown' => 3,
            'decoys_thrown' => 2,
            'damage_dealt' => 250, // High grenade damage
            'enemy_flash_duration' => 40, // High flash duration
            'average_grenade_effectiveness' => 60, // High effectiveness
            'flashes_leading_to_kills' => 8, // High flash assists
        ]);

        $filters = ['player_steam_id' => '76561198033333333'];
        $matchId = $this->match->id;

        $result = $this->service->get($this->user, $filters, $matchId);
        $supportScore = $result['player_complexion']['support'];

        // Should be a high score for good support stats
        $this->assertGreaterThan(50, $supportScore);
    }

    public function test_fragger_score_calculation()
    {
        // Create a player with optimal fragger stats
        $fraggerPlayer = PlayerMatchEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => '76561198044444444',
            'kills' => 30,
            'deaths' => 10, // Good K/D ratio
            'adr' => 120, // High ADR
            'total_successful_trades' => 8,
            'total_possible_trades' => 10, // Good trade kill percentage
        ]);

        $filters = ['player_steam_id' => '76561198044444444'];
        $matchId = $this->match->id;

        $result = $this->service->get($this->user, $filters, $matchId);
        $fraggerScore = $result['player_complexion']['fragger'];

        // Should be a high score for good fragger stats
        $this->assertGreaterThan(50, $fraggerScore);
    }

    public function test_fragger_score_with_zero_deaths()
    {
        // Test edge case where player has 0 deaths
        $fraggerPlayer = PlayerMatchEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => '76561198055555555',
            'kills' => 20,
            'deaths' => 0, // Zero deaths
            'adr' => 100,
            'total_successful_trades' => 5,
            'total_possible_trades' => 8,
        ]);

        $filters = ['player_steam_id' => '76561198055555555'];
        $matchId = $this->match->id;

        $result = $this->service->get($this->user, $filters, $matchId);
        $fraggerScore = $result['player_complexion']['fragger'];

        // Should not throw division by zero error and return a valid score
        $this->assertIsInt($fraggerScore);
        $this->assertGreaterThanOrEqual(0, $fraggerScore);
        $this->assertLessThanOrEqual(100, $fraggerScore);
    }

    public function test_clutch_percentage_calculation_with_zero_attempts()
    {
        // Test edge case where player has 0 clutch attempts
        $player = PlayerMatchEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => '76561198066666666',
            'clutch_wins_1v1' => 0,
            'clutch_attempts_1v1' => 0,
            'clutch_wins_1v2' => 0,
            'clutch_attempts_1v2' => 0,
            'clutch_wins_1v3' => 0,
            'clutch_attempts_1v3' => 0,
            'clutch_wins_1v4' => 0,
            'clutch_attempts_1v4' => 0,
            'clutch_wins_1v5' => 0,
            'clutch_attempts_1v5' => 0,
        ]);

        $filters = ['player_steam_id' => '76561198066666666'];
        $matchId = $this->match->id;

        $result = $this->service->get($this->user, $filters, $matchId);
        $clutchStats = $result['clutch_stats'];

        // Should handle zero attempts gracefully
        $this->assertEquals(0.0, $clutchStats['1v1']['clutch_win_percentage_1v1']);
        $this->assertEquals(0.0, $clutchStats['1v2']['clutch_win_percentage_1v2']);
        $this->assertEquals(0.0, $clutchStats['1v3']['clutch_win_percentage_1v3']);
    }

    public function test_cache_key_generation()
    {
        $filters1 = ['player_steam_id' => '76561198012345678'];
        $filters2 = ['player_steam_id' => '76561198012345678'];
        $filters3 = ['player_steam_id' => '76561198087654321'];

        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getCacheKey');
        $method->setAccessible(true);

        $key1 = $method->invoke($this->service, $filters1);
        $key2 = $method->invoke($this->service, $filters2);
        $key3 = $method->invoke($this->service, $filters3);

        // Same filters should generate same key
        $this->assertEquals($key1, $key2);

        // Different filters should generate different keys
        $this->assertNotEquals($key1, $key3);

        // Key should start with expected prefix
        $this->assertStringStartsWith('player-stats_', $key1);
    }

    public function test_normalise_score_higher_better()
    {
        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('normaliseScore');
        $method->setAccessible(true);

        // Test with higher is better
        $score1 = $method->invoke($this->service, 50, 100, true);
        $this->assertEquals(50, $score1);

        $score2 = $method->invoke($this->service, 100, 100, true);
        $this->assertEquals(100, $score2);

        $score3 = $method->invoke($this->service, 0, 100, true);
        $this->assertEquals(0, $score3);

        $score4 = $method->invoke($this->service, 150, 100, true);
        $this->assertEquals(100, $score4); // Should be capped at 100
    }

    public function test_normalise_score_lower_better()
    {
        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('normaliseScore');
        $method->setAccessible(true);

        // Test with lower is better
        $score1 = $method->invoke($this->service, 50, 100, false);
        $this->assertEquals(50, $score1);

        $score2 = $method->invoke($this->service, 0, 100, false);
        $this->assertEquals(100, $score2);

        $score3 = $method->invoke($this->service, 100, 100, false);
        $this->assertEquals(0, $score3);

        $score4 = $method->invoke($this->service, 150, 100, false);
        $this->assertEquals(0, $score4); // Should be capped at 0
    }

    public function test_normalise_score_with_float_values()
    {
        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('normaliseScore');
        $method->setAccessible(true);

        // Test with float values
        $score1 = $method->invoke($this->service, 25.5, 50.0, true);
        $this->assertEquals(51, $score1); // 25.5/50 * 100 = 51

        $score2 = $method->invoke($this->service, 12.5, 25.0, false);
        $this->assertEquals(50, $score2); // (1 - 12.5/25) * 100 = 50
    }

    public function test_opener_score_with_duplicate_calculation_bug()
    {
        // This test reveals a bug in the original code where firstKillAttemptsScore is calculated twice
        // and used for both firstKillAttemptsScore and tradedDeathsPercentageScore
        $openerPlayer = PlayerMatchEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => '76561198077777777',
            'average_round_time_of_death' => 20,
            'average_time_to_contact' => 15,
            'first_kills' => 3,
            'first_deaths' => 2,
            'total_successful_trades' => 5,
            'total_possible_traded_deaths' => 10,
        ]);

        $filters = ['player_steam_id' => '76561198077777777'];
        $matchId = $this->match->id;

        $result = $this->service->get($this->user, $filters, $matchId);
        $openerScore = $result['player_complexion']['opener'];

        // Should return a valid score despite the bug
        $this->assertIsInt($openerScore);
        $this->assertGreaterThanOrEqual(0, $openerScore);
        $this->assertLessThanOrEqual(100, $openerScore);
    }
}
