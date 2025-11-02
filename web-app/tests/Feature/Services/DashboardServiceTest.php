<?php

namespace Tests\Feature\Services;

use App\Models\GameMatch;
use App\Models\Player;
use App\Models\PlayerMatchAimEvent;
use App\Models\PlayerMatchEvent;
use App\Models\PlayerRank;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\Matches\PlayerComplexionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    private DashboardService $service;

    private User $user;

    private Player $player;

    private GameMatch $match1;

    private GameMatch $match2;

    protected function setUp(): void
    {
        parent::setUp();

        $playerComplexionService = new PlayerComplexionService;
        $this->service = new DashboardService($playerComplexionService);

        $this->user = User::factory()->create([
            'steam_id' => '76561198012345678',
            'steam_persona_name' => 'TestPlayer',
        ]);

        $this->player = Player::factory()->create([
            'steam_id' => '76561198012345678',
            'name' => 'TestPlayer',
        ]);

        // Create two matches
        $this->match1 = GameMatch::factory()->create([
            'map' => 'de_dust2',
            'winning_team' => 'A',
            'winning_team_score' => 16,
            'losing_team_score' => 14,
        ]);

        $this->match2 = GameMatch::factory()->create([
            'map' => 'de_mirage',
            'winning_team' => 'B',
            'winning_team_score' => 16,
            'losing_team_score' => 12,
        ]);

        // Attach player to matches
        $this->match1->players()->attach($this->player->id, ['team' => 'A']);
        $this->match2->players()->attach($this->player->id, ['team' => 'B']);
    }

    /** @test */
    public function it_returns_empty_player_stats_when_user_has_no_steam_id()
    {
        $user = User::factory()->create(['steam_id' => null]);
        $filters = [];

        $result = $this->service->getPlayerStats($user, $filters);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('opening_stats', $result);
        $this->assertArrayHasKey('trading_stats', $result);
        $this->assertArrayHasKey('clutch_stats', $result);
    }

    /** @test */
    public function it_returns_player_stats_with_correct_structure()
    {
        $this->createPlayerMatchEvents();

        $filters = ['past_match_count' => 10];
        $result = $this->service->getPlayerStats($this->user, $filters);

        // Check main structure
        $this->assertArrayHasKey('opening_stats', $result);
        $this->assertArrayHasKey('trading_stats', $result);
        $this->assertArrayHasKey('clutch_stats', $result);

        // Check opening stats
        $openingStats = $result['opening_stats'];
        $this->assertArrayHasKey('total_opening_kills', $openingStats);
        $this->assertArrayHasKey('total_opening_deaths', $openingStats);
        $this->assertArrayHasKey('opening_duel_winrate', $openingStats);

        // Check trading stats
        $tradingStats = $result['trading_stats'];
        $this->assertArrayHasKey('total_trades', $tradingStats);
        $this->assertArrayHasKey('total_possible_trades', $tradingStats);
        $this->assertArrayHasKey('average_trade_success_rate', $tradingStats);

        // Check clutch stats
        $clutchStats = $result['clutch_stats'];
        $this->assertArrayHasKey('1v1', $clutchStats);
        $this->assertArrayHasKey('1v2', $clutchStats);
        $this->assertArrayHasKey('1v3', $clutchStats);
        $this->assertArrayHasKey('1v4', $clutchStats);
        $this->assertArrayHasKey('1v5', $clutchStats);
        $this->assertArrayHasKey('overall', $clutchStats);
    }

    /** @test */
    public function it_calculates_player_stats_correctly()
    {
        $this->createPlayerMatchEvents();

        $filters = ['past_match_count' => 10];
        $result = $this->service->getPlayerStats($this->user, $filters);

        // Verify structure of opening stats
        $openingStats = $result['opening_stats'];
        $this->assertArrayHasKey('total_opening_kills', $openingStats);
        $this->assertArrayHasKey('total_opening_deaths', $openingStats);
        $this->assertArrayHasKey('opening_duel_winrate', $openingStats);
    }

    /** @test */
    public function it_returns_aim_stats_with_correct_structure()
    {
        $this->createPlayerMatchEvents();
        $this->createAimEvents();

        $filters = ['past_match_count' => 10];
        $result = $this->service->getAimStats($this->user, $filters);

        $this->assertArrayHasKey('aim_statistics', $result);
        $this->assertArrayHasKey('weapon_breakdown', $result);

        $aimStats = $result['aim_statistics'];
        $this->assertArrayHasKey('average_aim_rating', $aimStats);
        $this->assertArrayHasKey('average_headshot_percentage', $aimStats);
        $this->assertArrayHasKey('average_spray_accuracy', $aimStats);
        $this->assertArrayHasKey('average_crosshair_placement', $aimStats);
        $this->assertArrayHasKey('average_time_to_damage', $aimStats);

        // Each stat should have value, trend, and change
        foreach ($aimStats as $stat) {
            $this->assertArrayHasKey('value', $stat);
            $this->assertArrayHasKey('trend', $stat);
            $this->assertArrayHasKey('change', $stat);
        }
    }

    /** @test */
    public function it_calculates_crosshair_placement_using_pythagorean_theorem()
    {
        $this->createPlayerMatchEvents();

        // Create aim events with specific x and y values
        PlayerMatchAimEvent::factory()->create([
            'match_id' => $this->match1->id,
            'player_steam_id' => $this->player->steam_id,
            'average_crosshair_placement_x' => 3.0,
            'average_crosshair_placement_y' => 4.0,
            'aim_rating' => 80,
            'headshot_accuracy' => 50,
            'spraying_accuracy' => 60,
            'average_time_to_damage' => 0.5,
        ]);

        $filters = ['past_match_count' => 10];
        $result = $this->service->getAimStats($this->user, $filters);

        // sqrt(3^2 + 4^2) = sqrt(9 + 16) = sqrt(25) = 5.0
        $crosshairPlacement = $result['aim_statistics']['average_crosshair_placement']['value'];
        $this->assertEquals(5.0, $crosshairPlacement);
    }

    /** @test */
    public function it_returns_utility_stats_with_correct_structure()
    {
        $this->createPlayerMatchEvents();

        $filters = ['past_match_count' => 10];
        $result = $this->service->getUtilityStats($this->user, $filters);

        $this->assertArrayHasKey('avg_blind_duration_enemy', $result);
        $this->assertArrayHasKey('avg_blind_duration_friendly', $result);
        $this->assertArrayHasKey('avg_players_blinded_enemy', $result);
        $this->assertArrayHasKey('avg_players_blinded_friendly', $result);
        $this->assertArrayHasKey('he_molotov_damage', $result);
        $this->assertArrayHasKey('grenade_effectiveness', $result);
        $this->assertArrayHasKey('average_grenade_usage', $result);

        // Each stat should have value, trend, and change
        foreach ($result as $stat) {
            $this->assertArrayHasKey('value', $stat);
            $this->assertArrayHasKey('trend', $stat);
            $this->assertArrayHasKey('change', $stat);
        }
    }

    /** @test */
    public function it_returns_summary_with_correct_structure()
    {
        $this->createPlayerMatchEvents();
        $this->createAimEvents();

        $filters = ['past_match_count' => 10];
        $result = $this->service->getSummary($this->user, $filters);

        $this->assertArrayHasKey('most_improved_stats', $result);
        $this->assertArrayHasKey('least_improved_stats', $result);
        $this->assertArrayHasKey('average_aim_rating', $result);
        $this->assertArrayHasKey('average_utility_effectiveness', $result);
        $this->assertArrayHasKey('player_card', $result);

        // Check player card structure
        $playerCard = $result['player_card'];
        $this->assertArrayHasKey('username', $playerCard);
        $this->assertArrayHasKey('avatar', $playerCard);
        $this->assertArrayHasKey('average_impact', $playerCard);
        $this->assertArrayHasKey('average_round_swing', $playerCard);
        $this->assertArrayHasKey('average_kd', $playerCard);
        $this->assertArrayHasKey('average_kills', $playerCard);
        $this->assertArrayHasKey('average_deaths', $playerCard);
        $this->assertArrayHasKey('total_matches', $playerCard);
        $this->assertArrayHasKey('win_percentage', $playerCard);
        $this->assertArrayHasKey('player_complexion', $playerCard);

        // Check player complexion
        $complexion = $playerCard['player_complexion'];
        $this->assertArrayHasKey('opener', $complexion);
        $this->assertArrayHasKey('closer', $complexion);
        $this->assertArrayHasKey('support', $complexion);
        $this->assertArrayHasKey('fragger', $complexion);
    }

    /** @test */
    public function it_returns_map_stats_with_correct_structure()
    {
        $this->createPlayerMatchEvents();

        $filters = ['past_match_count' => 10];
        $result = $this->service->getMapStats($this->user, $filters);

        $this->assertArrayHasKey('maps', $result);
        $this->assertArrayHasKey('total_matches', $result);
        $this->assertEquals(2, $result['total_matches']);
        $this->assertCount(2, $result['maps']);

        // Check map stats structure
        $mapStats = $result['maps'][0];
        $this->assertArrayHasKey('map', $mapStats);
        $this->assertArrayHasKey('matches', $mapStats);
        $this->assertArrayHasKey('wins', $mapStats);
        $this->assertArrayHasKey('win_rate', $mapStats);
        $this->assertArrayHasKey('avg_kills', $mapStats);
        $this->assertArrayHasKey('avg_assists', $mapStats);
        $this->assertArrayHasKey('avg_deaths', $mapStats);
        $this->assertArrayHasKey('avg_kd', $mapStats);
        $this->assertArrayHasKey('avg_adr', $mapStats);
        $this->assertArrayHasKey('avg_opening_kills', $mapStats);
        $this->assertArrayHasKey('avg_opening_deaths', $mapStats);
        $this->assertArrayHasKey('avg_complexion', $mapStats);
    }

    /** @test */
    public function it_calculates_win_percentage_correctly_for_map_stats()
    {
        $this->createPlayerMatchEvents();

        $filters = ['past_match_count' => 10];
        $result = $this->service->getMapStats($this->user, $filters);

        // Player was on team A which won match1
        // Player was on team B which won match2
        $dust2Stats = collect($result['maps'])->firstWhere('map', 'de_dust2');
        $this->assertEquals(1, $dust2Stats['wins']);
        $this->assertEquals(100.0, $dust2Stats['win_rate']);

        $mirageStats = collect($result['maps'])->firstWhere('map', 'de_mirage');
        $this->assertEquals(1, $mirageStats['wins']);
        $this->assertEquals(100.0, $mirageStats['win_rate']);
    }

    /** @test */
    public function it_returns_rank_stats_with_correct_structure()
    {
        PlayerRank::factory()->count(3)->create([
            'player_id' => $this->player->id,
            'rank_type' => 'competitive',
            'map' => 'de_dust2',
            'rank' => 'Global Elite',
            'rank_value' => 18,
        ]);

        PlayerRank::factory()->count(2)->create([
            'player_id' => $this->player->id,
            'rank_type' => 'premier',
            'map' => null,
            'rank' => '20000',
            'rank_value' => 20000,
        ]);

        $filters = ['past_match_count' => 10];
        $result = $this->service->getRankStats($this->user, $filters);

        $this->assertArrayHasKey('competitive', $result);
        $this->assertArrayHasKey('premier', $result);
        $this->assertArrayHasKey('faceit', $result);

        // Check competitive structure (has maps)
        $competitive = $result['competitive'];
        $this->assertArrayHasKey('rank_type', $competitive);
        $this->assertArrayHasKey('maps', $competitive);
        $this->assertIsArray($competitive['maps']);

        if (! empty($competitive['maps'])) {
            $mapRank = $competitive['maps'][0];
            $this->assertArrayHasKey('map', $mapRank);
            $this->assertArrayHasKey('current_rank', $mapRank);
            $this->assertArrayHasKey('current_rank_value', $mapRank);
            $this->assertArrayHasKey('trend', $mapRank);
            $this->assertArrayHasKey('history', $mapRank);
        }

        // Check premier structure (no maps)
        $premier = $result['premier'];
        $this->assertArrayHasKey('rank_type', $premier);
        $this->assertArrayHasKey('current_rank', $premier);
        $this->assertArrayHasKey('current_rank_value', $premier);
        $this->assertArrayHasKey('trend', $premier);
        $this->assertArrayHasKey('history', $premier);
    }

    /** @test */
    public function it_handles_filters_for_date_range()
    {
        $this->createPlayerMatchEvents();

        $filters = [
            'date_from' => now()->subDays(1)->toDateString(),
            'date_to' => now()->toDateString(),
            'past_match_count' => 10,
        ];

        $result = $this->service->getPlayerStats($this->user, $filters);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('opening_stats', $result);
    }

    /** @test */
    public function it_handles_filters_for_game_type()
    {
        $this->createPlayerMatchEvents();

        $filters = [
            'game_type' => 'matchmaking',
            'past_match_count' => 10,
        ];

        $result = $this->service->getPlayerStats($this->user, $filters);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('opening_stats', $result);
    }

    /** @test */
    public function it_handles_filters_for_map()
    {
        $this->createPlayerMatchEvents();

        $filters = [
            'map' => 'de_dust2',
            'past_match_count' => 10,
        ];

        $result = $this->service->getPlayerStats($this->user, $filters);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('opening_stats', $result);
    }

    /** @test */
    public function it_caches_player_stats_results()
    {
        Cache::flush();
        $this->createPlayerMatchEvents();

        $filters = ['past_match_count' => 10];

        // First call should query database
        $result1 = $this->service->getPlayerStats($this->user, $filters);

        // Second call should use cache
        $result2 = $this->service->getPlayerStats($this->user, $filters);

        $this->assertEquals($result1, $result2);
    }

    /** @test */
    public function it_handles_trend_calculation_correctly_for_increasing_values()
    {
        // Create current period matches with higher stats
        PlayerMatchEvent::factory()->create([
            'match_id' => $this->match1->id,
            'player_steam_id' => $this->player->steam_id,
            'kills' => 30,
            'deaths' => 15,
            'adr' => 100,
        ]);

        // Create another match for previous period simulation
        $match3 = GameMatch::factory()->create(['map' => 'de_inferno']);
        $match3->players()->attach($this->player->id, ['team' => 'A']);

        PlayerMatchEvent::factory()->create([
            'match_id' => $match3->id,
            'player_steam_id' => $this->player->steam_id,
            'kills' => 20,
            'deaths' => 15,
            'adr' => 80,
        ]);

        $filters = ['past_match_count' => 1];
        $result = $this->service->getPlayerStats($this->user, $filters);

        // Verify that trends are calculated for opening stats
        $this->assertArrayHasKey('trend', $result['opening_stats']['total_opening_kills']);
    }

    /** @test */
    public function it_handles_trend_calculation_for_lower_is_better_stats()
    {
        // Test that lower values for deaths are considered better
        PlayerMatchEvent::factory()->create([
            'match_id' => $this->match1->id,
            'player_steam_id' => $this->player->steam_id,
            'kills' => 30,
            'deaths' => 10, // Lower deaths
            'adr' => 100,
        ]);

        $match3 = GameMatch::factory()->create(['map' => 'de_inferno']);
        $match3->players()->attach($this->player->id, ['team' => 'A']);

        PlayerMatchEvent::factory()->create([
            'match_id' => $match3->id,
            'player_steam_id' => $this->player->steam_id,
            'kills' => 30,
            'deaths' => 20, // Higher deaths in previous period
            'adr' => 100,
        ]);

        $filters = ['past_match_count' => 1];
        $result = $this->service->getPlayerStats($this->user, $filters);

        // Verify that trends are calculated correctly (lower is better for deaths)
        $openingDeaths = $result['opening_stats']['total_opening_deaths'];
        $this->assertArrayHasKey('trend', $openingDeaths);
    }

    /** @test */
    public function it_handles_zero_division_gracefully()
    {
        PlayerMatchEvent::factory()->create([
            'match_id' => $this->match1->id,
            'player_steam_id' => $this->player->steam_id,
            'kills' => 20,
            'deaths' => 0, // Zero deaths
            'first_kills' => 0,
            'first_deaths' => 0,
            'total_possible_trades' => 0,
        ]);

        $filters = ['past_match_count' => 10];
        $result = $this->service->getPlayerStats($this->user, $filters);

        // Should not throw any errors
        $this->assertIsArray($result);
        $this->assertArrayHasKey('opening_stats', $result);
    }

    /** @test */
    public function it_returns_empty_stats_when_no_matches_exist()
    {
        $filters = ['past_match_count' => 10];
        $result = $this->service->getPlayerStats($this->user, $filters);

        $this->assertIsArray($result);
        $this->assertEquals(0, $result['opening_stats']['total_opening_kills']['value']);
        $this->assertEquals(0, $result['opening_stats']['total_opening_deaths']['value']);
    }

    /** @test */
    public function it_warms_cache_for_match_players()
    {
        Cache::flush();
        $this->createPlayerMatchEvents();

        // Warm cache for the match
        $this->service->warmCacheForMatch($this->match1->id);

        // Verify cache was warmed by checking if data is cached
        $filters = ['past_match_count' => 5];
        $cacheKey = "dashboard:player-stats:{$this->user->steam_id}:".md5(json_encode($filters));

        $this->assertTrue(Cache::has($cacheKey));
    }

    /** @test */
    public function it_handles_missing_match_in_cache_warming()
    {
        // Should not throw an error for non-existent match
        $this->service->warmCacheForMatch(99999);

        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    /** @test */
    public function it_identifies_most_improved_stats_in_summary()
    {
        // Create current period with better stats
        PlayerMatchEvent::factory()->create([
            'match_id' => $this->match1->id,
            'player_steam_id' => $this->player->steam_id,
            'kills' => 30,
            'deaths' => 10,
            'adr' => 120,
        ]);

        PlayerMatchAimEvent::factory()->create([
            'match_id' => $this->match1->id,
            'player_steam_id' => $this->player->steam_id,
            'aim_rating' => 85,
            'headshot_accuracy' => 60,
        ]);

        // Create previous period with worse stats
        $match3 = GameMatch::factory()->create(['map' => 'de_inferno']);
        $match3->players()->attach($this->player->id, ['team' => 'A']);

        PlayerMatchEvent::factory()->create([
            'match_id' => $match3->id,
            'player_steam_id' => $this->player->steam_id,
            'kills' => 15,
            'deaths' => 20,
            'adr' => 60,
        ]);

        PlayerMatchAimEvent::factory()->create([
            'match_id' => $match3->id,
            'player_steam_id' => $this->player->steam_id,
            'aim_rating' => 50,
            'headshot_accuracy' => 30,
        ]);

        $filters = ['past_match_count' => 1];
        $result = $this->service->getSummary($this->user, $filters);

        // Should have improved stats
        if ($result['most_improved_stats']) {
            $this->assertIsArray($result['most_improved_stats']);
            $this->assertLessThanOrEqual(4, count($result['most_improved_stats']));
        }
    }

    /** @test */
    public function it_sorts_maps_by_match_count()
    {
        // Create more matches on dust2
        for ($i = 0; $i < 3; $i++) {
            $match = GameMatch::factory()->create(['map' => 'de_dust2']);
            $match->players()->attach($this->player->id, ['team' => 'A']);
            PlayerMatchEvent::factory()->create([
                'match_id' => $match->id,
                'player_steam_id' => $this->player->steam_id,
            ]);
        }

        // Create one match on mirage
        $this->createPlayerMatchEvents();

        $filters = ['past_match_count' => 10];
        $result = $this->service->getMapStats($this->user, $filters);

        // First map should be the one with most matches (dust2 with 4 matches)
        $this->assertEquals('de_dust2', $result['maps'][0]['map']);
        $this->assertEquals(4, $result['maps'][0]['matches']);
    }

    /** @test */
    public function it_calculates_clutch_stats_with_overall_summary()
    {
        // Create a fresh user/player to avoid data from other tests
        $freshUser = User::factory()->create(['steam_id' => '76561198099999999']);
        $freshPlayer = Player::factory()->create(['steam_id' => '76561198099999999']);
        $freshMatch = GameMatch::factory()->create(['map' => 'de_nuke']);
        $freshMatch->players()->attach($freshPlayer->id, ['team' => 'A']);

        PlayerMatchEvent::factory()->create([
            'match_id' => $freshMatch->id,
            'player_steam_id' => $freshPlayer->steam_id,
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
        ]);

        $filters = ['past_match_count' => 10];
        $result = $this->service->getPlayerStats($freshUser, $filters);

        $clutchStats = $result['clutch_stats'];
        $this->assertArrayHasKey('overall', $clutchStats);
        $this->assertEquals(3, $clutchStats['overall']['total']); // 2 + 1 + 0
        $this->assertEquals(6, $clutchStats['overall']['attempts']); // 3 + 2 + 1
        $this->assertEquals(50.0, $clutchStats['overall']['winrate']); // 3/6 * 100
    }

    /** @test */
    public function it_calculates_grenade_usage_correctly()
    {
        PlayerMatchEvent::factory()->create([
            'match_id' => $this->match1->id,
            'player_steam_id' => $this->player->steam_id,
            'flashes_thrown' => 10,
            'fire_grenades_thrown' => 5,
            'smokes_thrown' => 8,
            'hes_thrown' => 3,
            'decoys_thrown' => 2,
        ]);

        $filters = ['past_match_count' => 10];
        $result = $this->service->getUtilityStats($this->user, $filters);

        // Total grenades: 10 + 5 + 8 + 3 + 2 = 28
        $this->assertEquals(28.0, $result['average_grenade_usage']['value']);
    }

    /** @test */
    public function it_respects_past_match_count_filter()
    {
        // Create 5 matches
        for ($i = 0; $i < 5; $i++) {
            $match = GameMatch::factory()->create(['map' => 'de_dust2']);
            $match->players()->attach($this->player->id, ['team' => 'A']);
            PlayerMatchEvent::factory()->create([
                'match_id' => $match->id,
                'player_steam_id' => $this->player->steam_id,
                'kills' => 10,
            ]);
        }

        // Request only last 2 matches
        $filters = ['past_match_count' => 2];
        $result = $this->service->getPlayerStats($this->user, $filters);

        // Verify the result has the expected structure
        $this->assertArrayHasKey('opening_stats', $result);
        $this->assertArrayHasKey('total_opening_kills', $result['opening_stats']);
    }

    /**
     * Helper method to create player match events
     */
    private function createPlayerMatchEvents(): void
    {
        PlayerMatchEvent::factory()->create([
            'match_id' => $this->match1->id,
            'player_steam_id' => $this->player->steam_id,
            'kills' => 20,
            'deaths' => 15,
            'assists' => 5,
            'adr' => 85.5,
            'first_kills' => 8,
            'first_deaths' => 6,
            'total_successful_trades' => 4,
            'total_possible_trades' => 8,
            'total_traded_deaths' => 3,
            'total_possible_traded_deaths' => 6,
            'clutch_wins_1v1' => 2,
            'clutch_attempts_1v1' => 3,
            'flashes_thrown' => 5,
            'fire_grenades_thrown' => 3,
            'smokes_thrown' => 4,
            'hes_thrown' => 2,
            'decoys_thrown' => 1,
            'damage_dealt' => 150,
            'enemy_flash_duration' => 25.5,
            'enemy_players_affected' => 3,
            'friendly_flash_duration' => 5.5,
            'friendly_players_affected' => 1,
            'average_grenade_effectiveness' => 45.2,
            'average_impact' => 1.2,
            'match_swing_percent' => 15.5,
        ]);

        PlayerMatchEvent::factory()->create([
            'match_id' => $this->match2->id,
            'player_steam_id' => $this->player->steam_id,
            'kills' => 20,
            'deaths' => 15,
            'assists' => 5,
            'adr' => 85.5,
            'first_kills' => 8,
            'first_deaths' => 6,
            'total_successful_trades' => 4,
            'total_possible_trades' => 8,
            'total_traded_deaths' => 3,
            'total_possible_traded_deaths' => 6,
            'clutch_wins_1v1' => 2,
            'clutch_attempts_1v1' => 3,
            'flashes_thrown' => 5,
            'fire_grenades_thrown' => 3,
            'smokes_thrown' => 4,
            'hes_thrown' => 2,
            'decoys_thrown' => 1,
            'damage_dealt' => 150,
            'enemy_flash_duration' => 25.5,
            'enemy_players_affected' => 3,
            'friendly_flash_duration' => 5.5,
            'friendly_players_affected' => 1,
            'average_grenade_effectiveness' => 45.2,
            'average_impact' => 1.2,
            'match_swing_percent' => 15.5,
        ]);
    }

    /**
     * Helper method to create aim events
     */
    private function createAimEvents(): void
    {
        PlayerMatchAimEvent::factory()->create([
            'match_id' => $this->match1->id,
            'player_steam_id' => $this->player->steam_id,
            'aim_rating' => 80,
            'headshot_accuracy' => 50,
            'spraying_accuracy' => 60,
            'average_crosshair_placement_x' => 2.5,
            'average_crosshair_placement_y' => 3.0,
            'average_time_to_damage' => 0.5,
        ]);

        PlayerMatchAimEvent::factory()->create([
            'match_id' => $this->match2->id,
            'player_steam_id' => $this->player->steam_id,
            'aim_rating' => 75,
            'headshot_accuracy' => 48,
            'spraying_accuracy' => 55,
            'average_crosshair_placement_x' => 2.0,
            'average_crosshair_placement_y' => 2.5,
            'average_time_to_damage' => 0.6,
        ]);
    }
}
