<?php

namespace Tests\Feature\Controllers\Api;

use App\Enums\MatchType;
use App\Models\DemoProcessingJob;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Player $player;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'steam_id' => '76561198012345678',
        ]);

        $this->player = Player::factory()->create([
            'steam_id' => '76561198012345678',
        ]);
    }

    public function test_index_returns_unauthorized_for_unauthenticated_user()
    {
        $response = $this->getJson('/api/matches');

        $response->assertStatus(401);
    }

    public function test_index_returns_404_for_user_without_steam_id()
    {
        $user = User::factory()->create(['steam_id' => null]);

        $response = $this->actingAs($user)
            ->getJson('/api/matches');

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [],
            'pagination' => [
                'current_page' => 1,
                'per_page' => 10,
                'total' => 0,
                'last_page' => 0,
                'from' => 1,
                'to' => 0,
            ],
        ]);
    }

    public function test_index_returns_404_for_user_without_player()
    {
        $user = User::factory()->create(['steam_id' => '76561198087654321']);

        $response = $this->actingAs($user)
            ->getJson('/api/matches');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [],
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => 10,
                    'total' => 0,
                    'last_page' => 0,
                    'from' => 1,
                    'to' => 0,
                ],
            ]);
    }

    public function test_index_returns_matches_with_pagination()
    {
        // Create some matches for the player
        $matches = GameMatch::factory()->count(3)->create();

        foreach ($matches as $match) {
            $match->players()->attach($this->player->id, ['team' => 'A']);
        }

        $response = $this->actingAs($this->user)
            ->getJson('/api/matches?page=1&per_page=2');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'created_at',
                        'is_completed',
                        'match_details' => [
                            'id',
                            'map',
                            'winning_team_score',
                            'losing_team_score',
                            'winning_team',
                            'winning_team',
                            'match_type',
                            'created_at',
                        ],
                        'player_stats' => [
                            '*' => [
                                'player_name',
                                'player_kills',
                                'player_deaths',
                                'player_first_kill_differential',
                                'player_kill_death_ratio',
                                'player_adr',
                                'team',
                            ],
                        ],
                    ],
                ],
                'pagination' => [
                    'current_page',
                    'per_page',
                    'total',
                    'last_page',
                    'from',
                    'to',
                ],
            ]);
    }

    public function test_index_with_map_filter()
    {
        $match1 = GameMatch::factory()->create(['map' => 'de_mirage']);
        $match2 = GameMatch::factory()->create(['map' => 'de_inferno']);

        foreach ([$match1, $match2] as $match) {
            $match->players()->attach($this->player->id, ['team' => 'A']);
            // Create completed demo processing job for each match
            DemoProcessingJob::factory()->completed()->forMatch($match)->create();
        }

        $response = $this->actingAs($this->user)
            ->getJson('/api/matches?map=de_mirage');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals('de_mirage', $data[0]['match_details']['map']);
    }

    public function test_index_with_match_type_filter()
    {
        $match1 = GameMatch::factory()->create(['match_type' => MatchType::HLTV]);
        $match2 = GameMatch::factory()->create(['match_type' => MatchType::MATCHMAKING]);

        foreach ([$match1, $match2] as $match) {
            $match->players()->attach($this->player->id, ['team' => 'A']);
            // Create completed demo processing job for each match
            DemoProcessingJob::factory()->completed()->forMatch($match)->create();
        }

        $response = $this->actingAs($this->user)
            ->getJson('/api/matches?match_type=hltv');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals('hltv', $data[0]['match_details']['match_type']);
    }

    public function test_match_details_returns_unauthorized_for_unauthenticated_user()
    {
        $match = GameMatch::factory()->create();

        $response = $this->getJson("/api/matches/{$match->id}/details");

        $response->assertStatus(401);
    }

    public function test_match_details_returns_404_for_nonexistent_match()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/matches/99999/details');

        $response->assertStatus(404);
    }

    public function test_match_details_returns_match_details_for_accessible_match()
    {
        $match = GameMatch::factory()->create();
        $match->players()->attach($this->player->id, ['team' => 'A']);

        $response = $this->actingAs($this->user)
            ->getJson("/api/matches/{$match->id}/details");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'match' => [
                    'id',
                    'map',
                    'winning_team_score',
                    'losing_team_score',
                    'match_type',
                    'created_at',
                ],
                'player_did_win',
                'player_was_participant',
                'scoreboard' => [
                    '*' => [
                        'player_name',
                        'player_kills',
                        'player_deaths',
                        'player_kill_death_ratio',
                        'player_adr',
                        'team',
                    ],
                ],
            ]);
    }

    public function test_player_stats_returns_unauthorized_for_unauthenticated_user()
    {
        $match = GameMatch::factory()->create();

        $response = $this->getJson("/api/matches/{$match->id}/player-stats");

        $response->assertStatus(401);
    }

    public function test_player_stats_returns_404_for_nonexistent_match()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/matches/99999/player-stats');

        $response->assertStatus(404);
    }

    public function test_player_stats_returns_player_stats_for_accessible_match()
    {
        $match = GameMatch::factory()->create();
        $match->players()->attach($this->player->id, ['team' => 'A']);

        $response = $this->actingAs($this->user)
            ->getJson("/api/matches/{$match->id}/player-stats");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'player_complexion' => [
                    'opener_score',
                    'closer_score',
                    'support_score',
                    'fragger_score',
                ],
                'duels' => [
                    'total_duels',
                    'duels_won',
                    'duels_lost',
                    'duel_win_percentage',
                ],
                'clutch' => [
                    'total_clutches',
                    'clutches_won',
                    'clutches_lost',
                    'clutch_win_percentage',
                ],
                'deep_dive' => [
                    'first_kills',
                    'first_deaths',
                    'first_kill_differential',
                ],
            ]);
    }

    public function test_grenade_explorer_returns_unauthorized_for_unauthenticated_user()
    {
        $match = GameMatch::factory()->create();

        $response = $this->getJson("/api/matches/{$match->id}/grenade-explorer");

        $response->assertStatus(401);
    }

    public function test_grenade_explorer_returns_404_for_nonexistent_match()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/matches/99999/grenade-explorer');

        $response->assertStatus(404);
    }

    public function test_grenade_explorer_returns_grenade_data_for_accessible_match()
    {
        $match = GameMatch::factory()->create();
        $match->players()->attach($this->player->id, ['team' => 'A']);

        $response = $this->actingAs($this->user)
            ->getJson("/api/matches/{$match->id}/grenade-explorer");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'grenades' => [
                    '*' => [
                        'id',
                        'grenade_type',
                        'round_number',
                        'player_name',
                        'player_side',
                        'damage',
                        'flash_duration',
                    ],
                ],
                'filters' => [
                    'grenade_types',
                    'rounds',
                    'players',
                ],
            ]);
    }

    public function test_grenade_explorer_filter_options_returns_unauthorized_for_unauthenticated_user()
    {
        $match = GameMatch::factory()->create();

        $response = $this->getJson("/api/matches/{$match->id}/grenade-explorer/filter-options");

        $response->assertStatus(401);
    }

    public function test_grenade_explorer_filter_options_returns_filter_options_for_accessible_match()
    {
        $match = GameMatch::factory()->create();
        $match->players()->attach($this->player->id, ['team' => 'A']);

        $response = $this->actingAs($this->user)
            ->getJson("/api/matches/{$match->id}/grenade-explorer/filter-options");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'grenade_types',
                'rounds',
                'players',
            ]);
    }

    public function test_head_to_head_returns_unauthorized_for_unauthenticated_user()
    {
        $match = GameMatch::factory()->create();

        $response = $this->getJson("/api/matches/{$match->id}/head-to-head");

        $response->assertStatus(401);
    }

    public function test_head_to_head_returns_404_for_nonexistent_match()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/matches/99999/head-to-head');

        $response->assertStatus(404);
    }

    public function test_head_to_head_returns_comparison_for_accessible_match()
    {
        $match = GameMatch::factory()->create();
        $match->players()->attach($this->player->id, ['team' => 'A']);

        $response = $this->actingAs($this->user)
            ->getJson("/api/matches/{$match->id}/head-to-head?player1_steam_id={$this->player->steam_id}&player2_steam_id=76561198087654321");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'player1' => [
                    'player_name',
                    'kills',
                    'deaths',
                    'adr',
                ],
                'player2' => [
                    'player_name',
                    'kills',
                    'deaths',
                    'adr',
                ],
                'comparison' => [
                    'kills_difference',
                    'deaths_difference',
                    'adr_difference',
                ],
            ]);
    }

    public function test_top_role_players_returns_unauthorized_for_unauthenticated_user()
    {
        $match = GameMatch::factory()->create();

        $response = $this->getJson("/api/matches/{$match->id}/top-role-players");

        $response->assertStatus(401);
    }

    public function test_top_role_players_returns_404_for_nonexistent_match()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/matches/99999/top-role-players');

        $response->assertStatus(404);
    }

    public function test_top_role_players_returns_best_players_for_each_role()
    {
        $match = GameMatch::factory()->create();
        $match->players()->attach($this->player->id, ['team' => 'A']);

        $response = $this->actingAs($this->user)
            ->getJson("/api/matches/{$match->id}/top-role-players");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'opener' => [
                    'player_name',
                    'opener_score',
                ],
                'closer' => [
                    'player_name',
                    'closer_score',
                ],
                'support' => [
                    'player_name',
                    'support_score',
                ],
                'fragger' => [
                    'player_name',
                    'fragger_score',
                ],
            ]);
    }

    public function test_index_handles_pagination_parameters_correctly()
    {
        // Create multiple matches
        $matches = GameMatch::factory()->count(5)->create();

        foreach ($matches as $match) {
            $match->players()->attach($this->player->id, ['team' => 'A']);
        }

        $response = $this->actingAs($this->user)
            ->getJson('/api/matches?page=2&per_page=2');

        $response->assertStatus(200)
            ->assertJson([
                'pagination' => [
                    'current_page' => 2,
                    'per_page' => 2,
                ],
            ]);
    }

    public function test_index_handles_invalid_pagination_parameters()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/matches?page=0&per_page=100');

        $response->assertStatus(200)
            ->assertJson([
                'pagination' => [
                    'current_page' => 1, // Should be corrected to 1
                    'per_page' => 50, // Should be limited to 50
                ],
            ]);
    }

    public function test_index_handles_date_filters()
    {
        $match1 = GameMatch::factory()->create(['start_timestamp' => now()->subDays(5)]);
        $match2 = GameMatch::factory()->create(['start_timestamp' => now()->subDays(1)]);

        foreach ([$match1, $match2] as $match) {
            $match->players()->attach($this->player->id, ['team' => 'A']);
        }

        $response = $this->actingAs($this->user)
            ->getJson('/api/matches?date_from='.now()->subDays(2)->toDateString());

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(1, $data);
    }
}
