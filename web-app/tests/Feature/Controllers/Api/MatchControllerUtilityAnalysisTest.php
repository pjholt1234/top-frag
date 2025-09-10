<?php

namespace Tests\Feature\Controllers\Api;

use App\Enums\GrenadeType;
use App\Models\GameMatch;
use App\Models\GrenadeEvent;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchControllerUtilityAnalysisTest extends TestCase
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
        ]);

        $this->player = Player::factory()->create([
            'steam_id' => '76561198012345678',
        ]);

        $this->match = GameMatch::factory()->create([
            'map' => 'de_dust2',
            'winning_team_score' => 16,
            'losing_team_score' => 14,
        ]);

        $this->match->players()->attach($this->player->id, ['team' => 'A']);
    }

    public function test_utility_analysis_returns_unauthorized_for_unauthenticated_user()
    {
        $response = $this->getJson("/api/matches/{$this->match->id}/utility-analysis");

        $response->assertStatus(401);
    }

    public function test_utility_analysis_returns_404_for_user_without_steam_id()
    {
        $user = User::factory()->create(['steam_id' => null]);

        $response = $this->actingAs($user)
            ->getJson("/api/matches/{$this->match->id}/utility-analysis");

        $response->assertStatus(404);
    }

    public function test_utility_analysis_returns_404_for_nonexistent_match()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/matches/99999/utility-analysis');

        $response->assertStatus(404);
    }

    public function test_utility_analysis_returns_correct_structure()
    {
        GrenadeEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'grenade_type' => GrenadeType::FLASHBANG,
            'round_number' => 1,
            'effectiveness_rating' => 8,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/matches/{$this->match->id}/utility-analysis");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'utility_usage' => [
                    '*' => ['type', 'count', 'percentage'],
                ],
                'grenade_effectiveness' => [
                    '*' => ['round', 'effectiveness', 'total_grenades'],
                ],
                'grenade_timing' => [
                    '*' => ['type', 'timing_data'],
                ],
                'overall_stats' => [
                    'overall_grenade_rating',
                    'flash_stats' => [
                        'enemy_avg_duration',
                        'friendly_avg_duration',
                        'enemy_avg_blinded',
                        'friendly_avg_blinded',
                    ],
                    'he_stats' => ['avg_damage'],
                ],
                'players',
                'rounds',
                'current_user_steam_id',
            ]);
    }

    public function test_utility_analysis_with_player_filter()
    {
        $otherPlayer = Player::factory()->create(['steam_id' => '76561198087654321']);
        $this->match->players()->attach($otherPlayer->id, ['team' => 'B']);

        GrenadeEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'grenade_type' => GrenadeType::FLASHBANG,
        ]);

        GrenadeEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $otherPlayer->steam_id,
            'grenade_type' => GrenadeType::HE_GRENADE,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/matches/{$this->match->id}/utility-analysis?player_steam_id={$this->player->steam_id}");

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertCount(1, $data['utility_usage']);
        $this->assertEquals(GrenadeType::FLASHBANG->value, $data['utility_usage'][0]['type']);
    }

    public function test_utility_analysis_with_round_filter()
    {
        GrenadeEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'round_number' => 1,
            'grenade_type' => GrenadeType::FLASHBANG,
        ]);

        GrenadeEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'round_number' => 2,
            'grenade_type' => GrenadeType::HE_GRENADE,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/matches/{$this->match->id}/utility-analysis?round_number=1");

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertCount(1, $data['utility_usage']);
        $this->assertEquals(GrenadeType::FLASHBANG->value, $data['utility_usage'][0]['type']);
    }

    public function test_utility_analysis_with_all_rounds_filter()
    {
        GrenadeEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'round_number' => 1,
            'grenade_type' => GrenadeType::FLASHBANG,
        ]);

        GrenadeEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'round_number' => 2,
            'grenade_type' => GrenadeType::HE_GRENADE,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/matches/{$this->match->id}/utility-analysis?round_number=all");

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertCount(2, $data['utility_usage']);
    }

    public function test_utility_analysis_returns_404_when_no_data_available()
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/matches/{$this->match->id}/utility-analysis");

        $response->assertStatus(404)
            ->assertJson(['message' => 'Match not found or no data available']);
    }
}
