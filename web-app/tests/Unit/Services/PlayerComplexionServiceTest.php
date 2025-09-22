<?php

namespace Tests\Unit\Services;

use App\Models\PlayerMatchEvent;
use App\Services\Matches\PlayerComplexionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PlayerComplexionServiceTest extends TestCase
{
    use RefreshDatabase;

    private PlayerComplexionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PlayerComplexionService;
    }

    public function test_get_returns_empty_array_when_player_not_found()
    {
        $result = $this->service->get('non-existent-steam-id', 1);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_get_returns_complexion_data_for_existing_player()
    {
        // First create a match
        $match = \App\Models\GameMatch::factory()->create();

        $playerMatchEvent = PlayerMatchEvent::factory()->create([
            'match_id' => $match->id,
            'player_steam_id' => 'steam_123',
            'kills' => 20,
            'deaths' => 15,
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
            'clutch_attempts_1v2' => 2,
            'clutch_wins_1v3' => 0,
            'clutch_attempts_1v3' => 1,
            'clutch_wins_1v4' => 0,
            'clutch_attempts_1v4' => 0,
            'clutch_wins_1v5' => 0,
            'clutch_attempts_1v5' => 0,
            'flashes_thrown' => 15,
            'damage_dealt' => 150,
            'enemy_flash_duration' => 25.5,
            'average_grenade_effectiveness' => 45.2,
            'flashes_leading_to_kills' => 3,
            'adr' => 85.5,
        ]);

        $result = $this->service->get('steam_123', $match->id);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('opener', $result);
        $this->assertArrayHasKey('closer', $result);
        $this->assertArrayHasKey('support', $result);
        $this->assertArrayHasKey('fragger', $result);

        // All scores should be integers between 0 and 100
        foreach (['opener', 'closer', 'support', 'fragger'] as $role) {
            $this->assertIsInt($result[$role]);
            $this->assertGreaterThanOrEqual(0, $result[$role]);
            $this->assertLessThanOrEqual(100, $result[$role]);
        }
    }

    public function test_opener_score_calculation()
    {
        $match = \App\Models\GameMatch::factory()->create();

        $playerMatchEvent = PlayerMatchEvent::factory()->create([
            'match_id' => $match->id,
            'player_steam_id' => 'steam_123',
            'average_round_time_of_death' => 20, // Low time = good for opener
            'average_time_to_contact' => 15, // Low time = good for opener
            'first_kills' => 5,
            'first_deaths' => 2,
            'total_successful_trades' => 2,
            'total_possible_traded_deaths' => 4,
        ]);

        $result = $this->service->get('steam_123', $match->id);

        // Should have a high opener score due to low times and good first kill differential
        $this->assertGreaterThan(50, $result['opener']);
    }

    public function test_closer_score_calculation()
    {
        $match = \App\Models\GameMatch::factory()->create();

        $playerMatchEvent = PlayerMatchEvent::factory()->create([
            'match_id' => $match->id,
            'player_steam_id' => 'steam_123',
            'average_round_time_of_death' => 35, // High time = good for closer
            'average_time_to_contact' => 30, // High time = good for closer
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

        $result = $this->service->get('steam_123', $match->id);

        // Should have a good closer score due to high times and clutch wins
        $this->assertGreaterThan(40, $result['closer']);
    }

    public function test_support_score_calculation()
    {
        $match = \App\Models\GameMatch::factory()->create();

        $playerMatchEvent = PlayerMatchEvent::factory()->create([
            'match_id' => $match->id,
            'player_steam_id' => 'steam_123',
            'flashes_thrown' => 20,
            'damage_dealt' => 180,
            'enemy_flash_duration' => 25.0,
            'average_grenade_effectiveness' => 40.0,
            'flashes_leading_to_kills' => 4,
        ]);

        $result = $this->service->get('steam_123', $match->id);

        // Should have a good support score due to high grenade usage and effectiveness
        $this->assertGreaterThan(40, $result['support']);
    }

    public function test_fragger_score_calculation()
    {
        $match = \App\Models\GameMatch::factory()->create();

        $playerMatchEvent = PlayerMatchEvent::factory()->create([
            'match_id' => $match->id,
            'player_steam_id' => 'steam_123',
            'kills' => 25,
            'deaths' => 10,
            'adr' => 95.0,
            'total_successful_trades' => 6,
            'total_possible_trades' => 8,
        ]);

        $result = $this->service->get('steam_123', $match->id);

        // Should have a high fragger score due to good K/D ratio and ADR
        $this->assertGreaterThan(60, $result['fragger']);
    }

    public function test_fragger_score_with_zero_deaths()
    {
        $match = \App\Models\GameMatch::factory()->create();

        $playerMatchEvent = PlayerMatchEvent::factory()->create([
            'match_id' => $match->id,
            'player_steam_id' => 'steam_123',
            'kills' => 5,
            'deaths' => 0, // Zero deaths
            'adr' => 50.0,
            'total_successful_trades' => 2,
            'total_possible_trades' => 3,
        ]);

        $result = $this->service->get('steam_123', $match->id);

        // Should still calculate fragger score without division by zero
        $this->assertIsInt($result['fragger']);
        $this->assertGreaterThanOrEqual(0, $result['fragger']);
    }

    public function test_normalise_score_higher_better()
    {
        // Use reflection to test the private normaliseScore method
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
        // Use reflection to test the private normaliseScore method
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
        // Use reflection to test the private normaliseScore method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('normaliseScore');
        $method->setAccessible(true);

        // Test with float values
        $score1 = $method->invoke($this->service, 25.5, 50.0, true);
        $this->assertEquals(51, $score1); // 25.5/50 * 100 = 51

        $score2 = $method->invoke($this->service, 12.5, 25.0, false);
        $this->assertEquals(50, $score2); // (1 - 12.5/25) * 100 = 50
    }

    public function test_caching_behavior()
    {
        // Enable caching for this test
        \Illuminate\Support\Facades\Config::set('app.cache_enabled', true);

        // Create a match and PlayerMatchEvent for the test
        $match = \App\Models\GameMatch::factory()->create();

        PlayerMatchEvent::factory()->create([
            'match_id' => $match->id,
            'player_steam_id' => 'steam_123',
            'kills' => 20,
            'deaths' => 15,
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
            'clutch_attempts_1v2' => 2,
            'clutch_wins_1v3' => 0,
            'clutch_attempts_1v3' => 1,
            'clutch_wins_1v4' => 0,
            'clutch_attempts_1v4' => 0,
            'clutch_wins_1v5' => 0,
            'clutch_attempts_1v5' => 0,
            'flashes_thrown' => 15,
            'damage_dealt' => 150,
            'enemy_flash_duration' => 25.5,
            'average_grenade_effectiveness' => 45.2,
            'flashes_leading_to_kills' => 3,
            'adr' => 85.5,
        ]);

        // Mock the cache to verify caching is used
        Cache::shouldReceive('remember')
            ->once()
            ->andReturn([
                'opener' => 75,
                'closer' => 60,
                'support' => 80,
                'fragger' => 70,
            ]);

        $result = $this->service->get('steam_123', $match->id);

        $this->assertEquals(75, $result['opener']);
        $this->assertEquals(60, $result['closer']);
        $this->assertEquals(80, $result['support']);
        $this->assertEquals(70, $result['fragger']);
    }

    public function test_cache_key_generation()
    {
        // Use reflection to test the private getCacheKey method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getCacheKey');
        $method->setAccessible(true);

        $key1 = $method->invoke($this->service, 'steam_123');
        $key2 = $method->invoke($this->service, 'steam_456');

        $this->assertEquals('player-complexion_steam_123', $key1);
        $this->assertEquals('player-complexion_steam_456', $key2);
        $this->assertNotEquals($key1, $key2);
    }

    public function test_edge_case_with_zero_values()
    {
        $match = \App\Models\GameMatch::factory()->create();

        $playerMatchEvent = PlayerMatchEvent::factory()->create([
            'match_id' => $match->id,
            'player_steam_id' => 'steam_123',
            'kills' => 0,
            'deaths' => 0,
            'first_kills' => 0,
            'first_deaths' => 0,
            'average_round_time_of_death' => 0,
            'average_time_to_contact' => 0,
            'total_successful_trades' => 0,
            'total_possible_trades' => 0,
            'total_traded_deaths' => 0,
            'total_possible_traded_deaths' => 0,
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
            'flashes_thrown' => 0,
            'damage_dealt' => 0,
            'enemy_flash_duration' => 0,
            'average_grenade_effectiveness' => 0,
            'flashes_leading_to_kills' => 0,
            'adr' => 0,
        ]);

        $result = $this->service->get('steam_123', $match->id);

        // Should handle zero values gracefully
        $this->assertIsArray($result);
        $this->assertArrayHasKey('opener', $result);
        $this->assertArrayHasKey('closer', $result);
        $this->assertArrayHasKey('support', $result);
        $this->assertArrayHasKey('fragger', $result);

        // All scores should be valid integers
        foreach (['opener', 'closer', 'support', 'fragger'] as $role) {
            $this->assertIsInt($result[$role]);
            $this->assertGreaterThanOrEqual(0, $result[$role]);
            $this->assertLessThanOrEqual(100, $result[$role]);
        }
    }

    public function test_percentage_calculation_edge_cases()
    {
        $match = \App\Models\GameMatch::factory()->create();

        $playerMatchEvent = PlayerMatchEvent::factory()->create([
            'match_id' => $match->id,
            'player_steam_id' => 'steam_123',
            'total_successful_trades' => 0,
            'total_possible_trades' => 0, // Division by zero case
            'total_traded_deaths' => 0,
            'total_possible_traded_deaths' => 0, // Division by zero case
        ]);

        $result = $this->service->get('steam_123', $match->id);

        // Should handle division by zero gracefully
        $this->assertIsArray($result);
        $this->assertArrayHasKey('opener', $result);
        $this->assertArrayHasKey('fragger', $result);
    }

    public function test_weighted_mean_calculation()
    {
        // Use reflection to test the private calculateWeightedMean method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateWeightedMean');
        $method->setAccessible(true);

        // Test with equal weights
        $scores1 = [
            ['score' => 50, 'weight' => 1.0],
            ['score' => 70, 'weight' => 1.0],
            ['score' => 30, 'weight' => 1.0],
        ];
        $result1 = $method->invoke($this->service, $scores1);
        $this->assertEquals(50, $result1); // (50 + 70 + 30) / 3 = 50

        // Test with different weights
        $scores2 = [
            ['score' => 50, 'weight' => 2.0],
            ['score' => 70, 'weight' => 1.0],
            ['score' => 30, 'weight' => 1.0],
        ];
        $result2 = $method->invoke($this->service, $scores2);
        $this->assertEquals(50, $result2); // (50*2 + 70*1 + 30*1) / (2+1+1) = 200/4 = 50

        // Test with empty array
        $result3 = $method->invoke($this->service, []);
        $this->assertEquals(0, $result3);

        // Test with zero total weight
        $scores4 = [
            ['score' => 50, 'weight' => 0.0],
            ['score' => 70, 'weight' => 0.0],
        ];
        $result4 = $method->invoke($this->service, $scores4);
        $this->assertEquals(0, $result4);
    }
}
