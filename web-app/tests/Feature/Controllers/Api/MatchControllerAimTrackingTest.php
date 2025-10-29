<?php

namespace Tests\Feature\Controllers\Api;

use App\Models\GameMatch;
use App\Models\Player;
use App\Models\PlayerMatchAimEvent;
use App\Models\PlayerMatchAimWeaponEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchControllerAimTrackingTest extends TestCase
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
            'name' => 'TestPlayer',
        ]);

        $this->match = GameMatch::factory()->create();
        $this->match->players()->attach($this->player->id, ['team' => 'A']);
    }

    // ========== aimTracking endpoint tests ==========

    public function test_aim_tracking_returns_unauthorized_for_unauthenticated_user()
    {
        $response = $this->getJson("/api/matches/{$this->match->id}/aim-tracking");

        $response->assertStatus(401);
    }

    public function test_aim_tracking_returns_404_when_no_data_found()
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/matches/{$this->match->id}/aim-tracking?player_steam_id={$this->player->steam_id}");

        $response->assertStatus(404)
            ->assertJson([
                'message' => config('messaging.matches.not-found-error'),
            ]);
    }

    public function test_aim_tracking_returns_correct_data_structure()
    {
        PlayerMatchAimEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'shots_fired' => 100,
            'shots_hit' => 45,
            'accuracy_all_shots' => 45.00,
            'spraying_shots_fired' => 40,
            'spraying_shots_hit' => 15,
            'spraying_accuracy' => 37.50,
            'headshot_accuracy' => 28.50,
            'aim_rating' => 75.50,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/matches/{$this->match->id}/aim-tracking?player_steam_id={$this->player->steam_id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'match_id',
                'player_steam_id',
                'shots_fired',
                'shots_hit',
                'accuracy_all_shots',
                'spraying_shots_fired',
                'spraying_shots_hit',
                'spraying_accuracy',
                'average_crosshair_placement_x',
                'average_crosshair_placement_y',
                'headshot_accuracy',
                'average_time_to_damage',
                'head_hits_total',
                'upper_chest_hits_total',
                'chest_hits_total',
                'legs_hits_total',
                'aim_rating',
            ])
            ->assertJson([
                'match_id' => $this->match->id,
                'player_steam_id' => $this->player->steam_id,
                'shots_fired' => 100,
                'shots_hit' => 45,
                'accuracy_all_shots' => 45.00,
                'aim_rating' => 75.50,
            ]);
    }

    public function test_aim_tracking_requires_player_steam_id_parameter()
    {
        PlayerMatchAimEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/matches/{$this->match->id}/aim-tracking");

        // Should return 422 validation error when required parameter is missing
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['player_steam_id']);
    }

    public function test_aim_tracking_returns_404_for_nonexistent_match()
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/matches/99999/aim-tracking?player_steam_id={$this->player->steam_id}");

        $response->assertStatus(404);
    }

    // ========== aimTrackingWeapon endpoint tests ==========

    public function test_aim_tracking_weapon_returns_unauthorized_for_unauthenticated_user()
    {
        $response = $this->getJson("/api/matches/{$this->match->id}/aim-tracking/weapon");

        $response->assertStatus(401);
    }

    public function test_aim_tracking_weapon_returns_404_when_no_data_found()
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/matches/{$this->match->id}/aim-tracking/weapon?player_steam_id={$this->player->steam_id}");

        $response->assertStatus(404)
            ->assertJson([
                'message' => config('messaging.matches.not-found-error'),
            ]);
    }

    public function test_aim_tracking_weapon_returns_aggregated_data_when_no_weapon_specified()
    {
        PlayerMatchAimEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'shots_fired' => 150,
            'shots_hit' => 65,
            'accuracy_all_shots' => 43.33,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/matches/{$this->match->id}/aim-tracking/weapon?player_steam_id={$this->player->steam_id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'match_id',
                'player_steam_id',
                'weapon_name',
                'shots_fired',
                'shots_hit',
                'accuracy_all_shots',
                'spraying_shots_fired',
                'spraying_shots_hit',
                'spraying_accuracy',
                'average_crosshair_placement_x',
                'average_crosshair_placement_y',
                'headshot_accuracy',
                'head_hits_total',
                'upper_chest_hits_total',
                'chest_hits_total',
                'legs_hits_total',
            ])
            ->assertJson([
                'match_id' => $this->match->id,
                'player_steam_id' => $this->player->steam_id,
                'weapon_name' => null,
                'shots_fired' => 150,
                'shots_hit' => 65,
            ]);
    }

    public function test_aim_tracking_weapon_returns_specific_weapon_data()
    {
        PlayerMatchAimWeaponEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'weapon_name' => 'ak47',
            'shots_fired' => 80,
            'shots_hit' => 35,
            'accuracy_all_shots' => 43.75,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/matches/{$this->match->id}/aim-tracking/weapon?player_steam_id={$this->player->steam_id}&weapon_name=ak47");

        $response->assertStatus(200)
            ->assertJson([
                'match_id' => $this->match->id,
                'player_steam_id' => $this->player->steam_id,
                'weapon_name' => 'ak47',
                'shots_fired' => 80,
                'shots_hit' => 35,
                'accuracy_all_shots' => 43.75,
            ]);
    }

    public function test_aim_tracking_weapon_returns_404_for_nonexistent_weapon()
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/matches/{$this->match->id}/aim-tracking/weapon?player_steam_id={$this->player->steam_id}&weapon_name=awp");

        $response->assertStatus(404);
    }

    public function test_aim_tracking_weapon_returns_404_for_nonexistent_match()
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/matches/99999/aim-tracking/weapon?player_steam_id={$this->player->steam_id}");

        $response->assertStatus(404);
    }

    public function test_aim_tracking_weapon_requires_player_steam_id_parameter()
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/matches/{$this->match->id}/aim-tracking/weapon");

        // Should return 422 validation error when required parameter is missing
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['player_steam_id']);
    }

    // ========== aimTrackingFilterOptions endpoint tests ==========

    public function test_aim_tracking_filter_options_returns_unauthorized_for_unauthenticated_user()
    {
        $response = $this->getJson("/api/matches/{$this->match->id}/aim-tracking/filter-options");

        $response->assertStatus(401);
    }

    public function test_aim_tracking_filter_options_returns_404_when_no_match_found()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/matches/99999/aim-tracking/filter-options');

        $response->assertStatus(404);
    }

    public function test_aim_tracking_filter_options_returns_correct_structure()
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/matches/{$this->match->id}/aim-tracking/filter-options");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'players' => [
                    '*' => [
                        'steam_id',
                        'name',
                    ],
                ],
                'weapons',
                'current_user_steam_id',
            ])
            ->assertJson([
                'current_user_steam_id' => $this->user->steam_id,
            ]);
    }

    public function test_aim_tracking_filter_options_returns_all_players_in_match()
    {
        $player2 = Player::factory()->create([
            'steam_id' => '76561198087654321',
            'name' => 'TestPlayer2',
        ]);

        $this->match->players()->attach($player2->id, ['team' => 'B']);

        $response = $this->actingAs($this->user)
            ->getJson("/api/matches/{$this->match->id}/aim-tracking/filter-options");

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertCount(2, $data['players']);

        $playerIds = collect($data['players'])->pluck('steam_id')->toArray();
        $this->assertContains($this->player->steam_id, $playerIds);
        $this->assertContains($player2->steam_id, $playerIds);
    }

    public function test_aim_tracking_filter_options_returns_weapons_for_selected_player()
    {
        PlayerMatchAimWeaponEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'weapon_name' => 'ak47',
        ]);

        PlayerMatchAimWeaponEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'weapon_name' => 'awp',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/matches/{$this->match->id}/aim-tracking/filter-options?player_steam_id={$this->player->steam_id}");

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertArrayHasKey('weapons', $data);
        $this->assertCount(3, $data['weapons']); // 2 weapons + "All Weapons" option

        // Check "All Weapons" is first
        $this->assertEquals('all', $data['weapons'][0]['value']);
        $this->assertEquals('All Weapons', $data['weapons'][0]['label']);

        // Check weapon structure
        $weaponValues = collect($data['weapons'])->pluck('value')->toArray();
        $this->assertContains('ak47', $weaponValues);
        $this->assertContains('awp', $weaponValues);
    }

    public function test_aim_tracking_filter_options_returns_empty_weapons_when_no_player_selected()
    {
        PlayerMatchAimWeaponEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'weapon_name' => 'ak47',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/matches/{$this->match->id}/aim-tracking/filter-options");

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertArrayHasKey('weapons', $data);
        $this->assertEmpty($data['weapons']);
    }

    public function test_aim_tracking_filter_options_returns_formatted_weapon_names()
    {
        PlayerMatchAimWeaponEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'weapon_name' => 'ak47',
        ]);

        PlayerMatchAimWeaponEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'weapon_name' => 'usp_silencer',
        ]);

        PlayerMatchAimWeaponEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'weapon_name' => 'm4a1_silencer',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/matches/{$this->match->id}/aim-tracking/filter-options?player_steam_id={$this->player->steam_id}");

        $response->assertStatus(200);

        $data = $response->json();
        $weapons = collect($data['weapons']);

        // Find AK47
        $ak47 = $weapons->firstWhere('value', 'ak47');
        $this->assertEquals('AK-47', $ak47['label']);

        // Find USP-S
        $usp = $weapons->firstWhere('value', 'usp_silencer');
        $this->assertEquals('USP-S', $usp['label']);

        // Find M4A1-S
        $m4a1s = $weapons->firstWhere('value', 'm4a1_silencer');
        $this->assertEquals('M4A1-S', $m4a1s['label']);
    }

    public function test_aim_tracking_handles_multiple_weapons_for_same_player()
    {
        // Create multiple weapon events for the same player
        $weapons = ['ak47', 'awp', 'm4a1', 'deagle', 'glock'];

        foreach ($weapons as $weapon) {
            PlayerMatchAimWeaponEvent::factory()->create([
                'match_id' => $this->match->id,
                'player_steam_id' => $this->player->steam_id,
                'weapon_name' => $weapon,
            ]);
        }

        $response = $this->actingAs($this->user)
            ->getJson("/api/matches/{$this->match->id}/aim-tracking/filter-options?player_steam_id={$this->player->steam_id}");

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertCount(6, $data['weapons']); // 5 weapons + "All Weapons" option

        $weaponValues = collect($data['weapons'])->pluck('value')->toArray();
        $this->assertContains('all', $weaponValues);
        foreach ($weapons as $weapon) {
            $this->assertContains($weapon, $weaponValues);
        }
    }

    public function test_aim_tracking_endpoints_handle_nonexistent_player_gracefully()
    {
        // Test with a player that doesn't exist in the match
        $nonexistentSteamId = '76561198099999999';

        $response = $this->actingAs($this->user)
            ->getJson("/api/matches/{$this->match->id}/aim-tracking?player_steam_id={$nonexistentSteamId}");

        $response->assertStatus(404);
    }

    public function test_aim_tracking_returns_correct_numeric_values()
    {
        PlayerMatchAimEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'shots_fired' => 200,
            'shots_hit' => 90,
            'accuracy_all_shots' => 45.00,
            'spraying_shots_fired' => 80,
            'spraying_shots_hit' => 28,
            'spraying_accuracy' => 35.00,
            'average_crosshair_placement_x' => 1.234,
            'average_crosshair_placement_y' => -2.567,
            'headshot_accuracy' => 31.11,
            'average_time_to_damage' => 0.85,
            'head_hits_total' => 28,
            'upper_chest_hits_total' => 18,
            'chest_hits_total' => 30,
            'legs_hits_total' => 14,
            'aim_rating' => 82.50,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/matches/{$this->match->id}/aim-tracking?player_steam_id={$this->player->steam_id}");

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertEquals(200, $data['shots_fired']);
        $this->assertEquals(90, $data['shots_hit']);
        $this->assertEquals(45.00, $data['accuracy_all_shots']);
        $this->assertEquals(1.234, $data['average_crosshair_placement_x']);
        $this->assertEquals(-2.567, $data['average_crosshair_placement_y']);
        $this->assertEquals(82.50, $data['aim_rating']);
    }

    public function test_aim_tracking_weapon_handles_different_weapons()
    {
        $weapons = [
            'ak47' => ['shots' => 100, 'hits' => 40],
            'awp' => ['shots' => 50, 'hits' => 35],
            'm4a1_silencer' => ['shots' => 80, 'hits' => 32],
        ];

        foreach ($weapons as $weaponName => $stats) {
            PlayerMatchAimWeaponEvent::factory()->create([
                'match_id' => $this->match->id,
                'player_steam_id' => $this->player->steam_id,
                'weapon_name' => $weaponName,
                'shots_fired' => $stats['shots'],
                'shots_hit' => $stats['hits'],
            ]);
        }

        // Test each weapon individually
        foreach ($weapons as $weaponName => $stats) {
            $response = $this->actingAs($this->user)
                ->getJson("/api/matches/{$this->match->id}/aim-tracking/weapon?player_steam_id={$this->player->steam_id}&weapon_name={$weaponName}");

            $response->assertStatus(200)
                ->assertJson([
                    'weapon_name' => $weaponName,
                    'shots_fired' => $stats['shots'],
                    'shots_hit' => $stats['hits'],
                ]);
        }
    }
}
