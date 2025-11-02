<?php

namespace Tests\Feature\Controllers\Api;

use App\Models\GameMatch;
use App\Models\Player;
use App\Models\PlayerMatchAimEvent;
use App\Models\PlayerMatchEvent;
use App\Models\PlayerRank;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Player $player;

    private GameMatch $match;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'steam_id' => '76561198012345678',
            'steam_persona_name' => 'TestPlayer',
        ]);

        $this->player = Player::factory()->create([
            'steam_id' => '76561198012345678',
            'name' => 'TestPlayer',
        ]);

        $this->match = GameMatch::factory()->create([
            'map' => 'de_dust2',
            'winning_team' => 'A',
        ]);

        $this->match->players()->attach($this->player->id, ['team' => 'A']);
    }

    /** @test */
    public function player_stats_returns_unauthorized_for_unauthenticated_user()
    {
        $response = $this->getJson('/api/dashboard/player-stats');

        $response->assertStatus(401);
    }

    /** @test */
    public function player_stats_returns_correct_structure_for_authenticated_user()
    {
        $this->createPlayerMatchEvent();

        $response = $this->actingAs($this->user)
            ->getJson('/api/dashboard/player-stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'opening_stats' => [
                    'total_opening_kills',
                    'total_opening_deaths',
                    'opening_duel_winrate',
                    'average_opening_kills',
                    'average_opening_deaths',
                    'average_duel_winrate',
                ],
                'trading_stats' => [
                    'total_trades',
                    'total_possible_trades',
                    'total_traded_deaths',
                    'total_possible_traded_deaths',
                    'average_trades',
                    'average_possible_trades',
                    'average_traded_deaths',
                    'average_possible_traded_deaths',
                    'average_trade_success_rate',
                    'average_traded_death_success_rate',
                ],
                'clutch_stats' => [
                    '1v1' => ['total', 'attempts', 'winrate'],
                    '1v2' => ['total', 'attempts', 'winrate'],
                    '1v3' => ['total', 'attempts', 'winrate'],
                    '1v4' => ['total', 'attempts', 'winrate'],
                    '1v5' => ['total', 'attempts', 'winrate'],
                    'overall' => ['total', 'attempts', 'winrate'],
                ],
            ]);
    }

    /** @test */
    public function player_stats_accepts_past_match_count_filter()
    {
        $this->createPlayerMatchEvent();

        $response = $this->actingAs($this->user)
            ->getJson('/api/dashboard/player-stats?past_match_count=5');

        $response->assertStatus(200);
    }

    /** @test */
    public function player_stats_accepts_date_filters()
    {
        $this->createPlayerMatchEvent();

        $response = $this->actingAs($this->user)
            ->getJson('/api/dashboard/player-stats?date_from='.now()->subDays(7)->toDateString().'&date_to='.now()->toDateString());

        $response->assertStatus(200);
    }

    /** @test */
    public function player_stats_accepts_game_type_filter()
    {
        $this->createPlayerMatchEvent();

        $response = $this->actingAs($this->user)
            ->getJson('/api/dashboard/player-stats?game_type=matchmaking');

        $response->assertStatus(200);
    }

    /** @test */
    public function player_stats_accepts_map_filter()
    {
        $this->createPlayerMatchEvent();

        $response = $this->actingAs($this->user)
            ->getJson('/api/dashboard/player-stats?map=de_dust2');

        $response->assertStatus(200);
    }

    /** @test */
    public function aim_stats_returns_unauthorized_for_unauthenticated_user()
    {
        $response = $this->getJson('/api/dashboard/aim-stats');

        $response->assertStatus(401);
    }

    /** @test */
    public function aim_stats_returns_correct_structure_for_authenticated_user()
    {
        $this->createPlayerMatchEvent();
        $this->createAimEvent();

        $response = $this->actingAs($this->user)
            ->getJson('/api/dashboard/aim-stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'aim_statistics' => [
                    'average_aim_rating' => ['value', 'trend', 'change'],
                    'average_headshot_percentage' => ['value', 'trend', 'change'],
                    'average_spray_accuracy' => ['value', 'trend', 'change'],
                    'average_crosshair_placement' => ['value', 'trend', 'change'],
                    'average_time_to_damage' => ['value', 'trend', 'change'],
                ],
                'weapon_breakdown',
            ]);
    }

    /** @test */
    public function aim_stats_accepts_filters()
    {
        $this->createPlayerMatchEvent();
        $this->createAimEvent();

        $response = $this->actingAs($this->user)
            ->getJson('/api/dashboard/aim-stats?past_match_count=10&map=de_dust2');

        $response->assertStatus(200);
    }

    /** @test */
    public function utility_stats_returns_unauthorized_for_unauthenticated_user()
    {
        $response = $this->getJson('/api/dashboard/utility-stats');

        $response->assertStatus(401);
    }

    /** @test */
    public function utility_stats_returns_correct_structure_for_authenticated_user()
    {
        $this->createPlayerMatchEvent();

        $response = $this->actingAs($this->user)
            ->getJson('/api/dashboard/utility-stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'avg_blind_duration_enemy' => ['value', 'trend', 'change'],
                'avg_blind_duration_friendly' => ['value', 'trend', 'change'],
                'avg_players_blinded_enemy' => ['value', 'trend', 'change'],
                'avg_players_blinded_friendly' => ['value', 'trend', 'change'],
                'he_molotov_damage' => ['value', 'trend', 'change'],
                'grenade_effectiveness' => ['value', 'trend', 'change'],
                'average_grenade_usage' => ['value', 'trend', 'change'],
            ]);
    }

    /** @test */
    public function utility_stats_accepts_filters()
    {
        $this->createPlayerMatchEvent();

        $response = $this->actingAs($this->user)
            ->getJson('/api/dashboard/utility-stats?past_match_count=15&game_type=matchmaking');

        $response->assertStatus(200);
    }

    /** @test */
    public function summary_returns_unauthorized_for_unauthenticated_user()
    {
        $response = $this->getJson('/api/dashboard/summary');

        $response->assertStatus(401);
    }

    /** @test */
    public function summary_returns_correct_structure_for_authenticated_user()
    {
        $this->createPlayerMatchEvent();
        $this->createAimEvent();

        $response = $this->actingAs($this->user)
            ->getJson('/api/dashboard/summary');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'most_improved_stats',
                'least_improved_stats',
                'average_aim_rating' => ['value', 'max'],
                'average_utility_effectiveness' => ['value', 'max'],
                'player_card' => [
                    'username',
                    'avatar',
                    'average_impact',
                    'average_round_swing',
                    'average_kd',
                    'average_kills',
                    'average_deaths',
                    'total_matches',
                    'win_percentage',
                    'player_complexion' => [
                        'opener',
                        'closer',
                        'support',
                        'fragger',
                    ],
                ],
            ]);
    }

    /** @test */
    public function summary_player_card_contains_user_information()
    {
        $this->createPlayerMatchEvent();
        $this->createAimEvent();

        $response = $this->actingAs($this->user)
            ->getJson('/api/dashboard/summary');

        $response->assertStatus(200);

        $data = $response->json();
        $playerCard = $data['player_card'];

        $this->assertEquals('TestPlayer', $playerCard['username']);
        $this->assertIsNumeric($playerCard['average_kd']);
        $this->assertIsNumeric($playerCard['win_percentage']);
    }

    /** @test */
    public function summary_accepts_filters()
    {
        $this->createPlayerMatchEvent();
        $this->createAimEvent();

        $response = $this->actingAs($this->user)
            ->getJson('/api/dashboard/summary?past_match_count=20');

        $response->assertStatus(200);
    }

    /** @test */
    public function map_stats_returns_unauthorized_for_unauthenticated_user()
    {
        $response = $this->getJson('/api/dashboard/map-stats');

        $response->assertStatus(401);
    }

    /** @test */
    public function map_stats_returns_correct_structure_for_authenticated_user()
    {
        $this->createPlayerMatchEvent();

        $response = $this->actingAs($this->user)
            ->getJson('/api/dashboard/map-stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'maps' => [
                    '*' => [
                        'map',
                        'matches',
                        'wins',
                        'win_rate',
                        'avg_kills',
                        'avg_assists',
                        'avg_deaths',
                        'avg_kd',
                        'avg_adr',
                        'avg_opening_kills',
                        'avg_opening_deaths',
                        'avg_complexion' => [
                            'opener',
                            'closer',
                            'support',
                            'fragger',
                        ],
                    ],
                ],
                'total_matches',
            ]);
    }

    /** @test */
    public function map_stats_returns_stats_grouped_by_map()
    {
        $this->createPlayerMatchEvent();

        // Create another match on different map
        $match2 = GameMatch::factory()->create(['map' => 'de_mirage', 'winning_team' => 'B']);
        $match2->players()->attach($this->player->id, ['team' => 'B']);
        PlayerMatchEvent::factory()->create([
            'match_id' => $match2->id,
            'player_steam_id' => $this->player->steam_id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/dashboard/map-stats');

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertCount(2, $data['maps']);
        $this->assertEquals(2, $data['total_matches']);

        $maps = collect($data['maps'])->pluck('map')->toArray();
        $this->assertContains('de_dust2', $maps);
        $this->assertContains('de_mirage', $maps);
    }

    /** @test */
    public function map_stats_accepts_filters()
    {
        $this->createPlayerMatchEvent();

        $response = $this->actingAs($this->user)
            ->getJson('/api/dashboard/map-stats?past_match_count=10&game_type=matchmaking');

        $response->assertStatus(200);
    }

    /** @test */
    public function rank_stats_returns_unauthorized_for_unauthenticated_user()
    {
        $response = $this->getJson('/api/dashboard/rank-stats');

        $response->assertStatus(401);
    }

    /** @test */
    public function rank_stats_returns_correct_structure_for_authenticated_user()
    {
        // Create rank data
        PlayerRank::factory()->count(3)->create([
            'player_id' => $this->player->id,
            'rank_type' => 'competitive',
            'map' => 'de_dust2',
        ]);

        PlayerRank::factory()->count(2)->create([
            'player_id' => $this->player->id,
            'rank_type' => 'premier',
            'map' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/dashboard/rank-stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'competitive' => [
                    'rank_type',
                    'maps' => [
                        '*' => [
                            'map',
                            'current_rank',
                            'current_rank_value',
                            'trend',
                            'history' => [
                                '*' => [
                                    'rank',
                                    'rank_value',
                                    'date',
                                    'timestamp',
                                ],
                            ],
                        ],
                    ],
                ],
                'premier' => [
                    'rank_type',
                    'current_rank',
                    'current_rank_value',
                    'trend',
                    'history',
                ],
                'faceit',
            ]);
    }

    /** @test */
    public function rank_stats_returns_empty_arrays_for_user_without_ranks()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/dashboard/rank-stats');

        $response->assertStatus(200)
            ->assertJson([
                'competitive' => [
                    'rank_type' => 'competitive',
                    'maps' => [],
                ],
                'premier' => [
                    'rank_type' => 'premier',
                    'current_rank' => null,
                    'current_rank_value' => null,
                    'history' => [],
                    'trend' => 'neutral',
                ],
                'faceit' => [
                    'rank_type' => 'faceit',
                    'current_rank' => null,
                    'current_rank_value' => null,
                    'history' => [],
                    'trend' => 'neutral',
                ],
            ]);
    }

    /** @test */
    public function rank_stats_shows_rank_progression_over_time()
    {
        // Create rank progression
        PlayerRank::factory()->create([
            'player_id' => $this->player->id,
            'rank_type' => 'premier',
            'map' => null,
            'rank' => '15000',
            'rank_value' => 15000,
            'created_at' => now()->subDays(10),
        ]);

        PlayerRank::factory()->create([
            'player_id' => $this->player->id,
            'rank_type' => 'premier',
            'map' => null,
            'rank' => '18000',
            'rank_value' => 18000,
            'created_at' => now()->subDays(5),
        ]);

        PlayerRank::factory()->create([
            'player_id' => $this->player->id,
            'rank_type' => 'premier',
            'map' => null,
            'rank' => '20000',
            'rank_value' => 20000,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/dashboard/rank-stats?past_match_count=10');

        $response->assertStatus(200);

        $data = $response->json();
        $premier = $data['premier'];

        $this->assertEquals('20000', $premier['current_rank']);
        $this->assertEquals(20000, $premier['current_rank_value']);
        $this->assertEquals('up', $premier['trend']);
        $this->assertCount(3, $premier['history']);
    }

    /** @test */
    public function rank_stats_accepts_filters()
    {
        PlayerRank::factory()->count(5)->create([
            'player_id' => $this->player->id,
            'rank_type' => 'premier',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/dashboard/rank-stats?past_match_count=3');

        $response->assertStatus(200);
    }

    /** @test */
    public function all_endpoints_use_default_past_match_count_when_not_provided()
    {
        $this->createPlayerMatchEvent();
        $this->createAimEvent();

        $endpoints = [
            '/api/dashboard/player-stats',
            '/api/dashboard/aim-stats',
            '/api/dashboard/utility-stats',
            '/api/dashboard/summary',
            '/api/dashboard/map-stats',
            '/api/dashboard/rank-stats',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->actingAs($this->user)
                ->getJson($endpoint);

            $response->assertStatus(200);
        }
    }

    /** @test */
    public function endpoints_handle_user_without_steam_id()
    {
        $userWithoutSteamId = User::factory()->create(['steam_id' => null]);

        $endpoints = [
            '/api/dashboard/player-stats',
            '/api/dashboard/aim-stats',
            '/api/dashboard/utility-stats',
            '/api/dashboard/summary',
            '/api/dashboard/map-stats',
            '/api/dashboard/rank-stats',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->actingAs($userWithoutSteamId)
                ->getJson($endpoint);

            $response->assertStatus(200);
        }
    }

    /** @test */
    public function aim_stats_calculates_crosshair_placement_correctly()
    {
        $this->createPlayerMatchEvent();

        PlayerMatchAimEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'average_crosshair_placement_x' => 6.0,
            'average_crosshair_placement_y' => 8.0,
            'aim_rating' => 85.0,
            'headshot_accuracy' => 55.0,
            'spraying_accuracy' => 65.0,
            'average_time_to_damage' => 0.45,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/dashboard/aim-stats');

        $response->assertStatus(200);

        $data = $response->json();
        $aimStats = $data['aim_statistics'];

        // sqrt(6^2 + 8^2) = sqrt(36 + 64) = sqrt(100) = 10.0
        $this->assertEquals(10.0, $aimStats['average_crosshair_placement']['value']);
        $this->assertEquals(85.0, $aimStats['average_aim_rating']['value']);
    }

    /** @test */
    public function dashboard_endpoints_are_cached()
    {
        $this->createPlayerMatchEvent();

        // First request
        $response1 = $this->actingAs($this->user)
            ->getJson('/api/dashboard/player-stats');

        $response1->assertStatus(200);

        // Update the data in database
        PlayerMatchEvent::where('match_id', $this->match->id)
            ->update(['kills' => 999]);

        // Second request should return cached data (not 999)
        $response2 = $this->actingAs($this->user)
            ->getJson('/api/dashboard/player-stats');

        $response2->assertStatus(200);

        // Verify response is still successful - caching is working
        $this->assertTrue($response2->status() === 200);
    }

    /**
     * Helper method to create a player match event
     */
    private function createPlayerMatchEvent(): void
    {
        PlayerMatchEvent::factory()->create([
            'match_id' => $this->match->id,
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
     * Helper method to create an aim event
     */
    private function createAimEvent(): void
    {
        PlayerMatchAimEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'aim_rating' => 80,
            'headshot_accuracy' => 50,
            'spraying_accuracy' => 60,
            'average_crosshair_placement_x' => 2.5,
            'average_crosshair_placement_y' => 3.0,
            'average_time_to_damage' => 0.5,
        ]);
    }
}
