<?php

namespace Tests\Feature\Controllers\Api;

use App\Enums\MatchType;
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
                'per_page' => 1,
                'total' => 0,
                'last_page' => 1,
                'from' => 1,
                'to' => 1,
            ],
        ]);
    }

    public function test_index_returns_empty_data_for_user_without_player()
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
                    'last_page' => 1,
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
        }

        $response = $this->actingAs($this->user)
            ->getJson('/api/matches?match_type=hltv');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals('hltv', $data[0]['match_details']['match_type']);
    }
}
