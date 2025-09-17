<?php

namespace Tests\Feature\Controllers\Api;

use App\Models\GameMatch;
use App\Models\GrenadeFavourite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrenadeFavouriteControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private GameMatch $match;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'steam_id' => 'STEAM_123456789',
        ]);

        $this->match = GameMatch::factory()->create([
            'map' => 'de_dust2',
        ]);
    }

    public function test_index_returns_user_favourites(): void
    {
        // Create a player record for the steam_id that will be used in the factory
        $player = \App\Models\Player::factory()->create([
            'steam_id' => 'STEAM_123456789',
            'name' => 'Test Player',
        ]);

        // Create some favourites for the user
        GrenadeFavourite::factory()->create([
            'user_id' => $this->user->id,
            'match_id' => $this->match->id,
            'player_steam_id' => $player->steam_id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/grenade-favourites');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'grenades' => [
                    '*' => [
                        'id',
                        'match_id',
                        'user_id',
                        'round_number',
                        'round_time',
                        'tick_timestamp',
                        'player_steam_id',
                        'player_side',
                        'grenade_type',
                        'map',
                        'player_name',
                    ],
                ],
            ]);
    }

    public function test_index_filters_by_match_id(): void
    {
        $match2 = GameMatch::factory()->create();

        // Create a player record for the steam_id that will be used in the factory
        $player = \App\Models\Player::factory()->create([
            'steam_id' => 'STEAM_123456789',
            'name' => 'Test Player',
        ]);

        GrenadeFavourite::factory()->create([
            'user_id' => $this->user->id,
            'match_id' => $this->match->id,
            'player_steam_id' => $player->steam_id,
        ]);

        GrenadeFavourite::factory()->create([
            'user_id' => $this->user->id,
            'match_id' => $match2->id,
            'player_steam_id' => $player->steam_id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/grenade-favourites?map=de_dust2&match_id=' . $this->match->id);

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('grenades'));
        $this->assertEquals($this->match->id, $response->json('grenades.0.match_id'));
    }

    public function test_create_adds_new_favourite(): void
    {
        $favouriteData = [
            'match_id' => $this->match->id,
            'round_number' => 1,
            'round_time' => 120.5,
            'tick_timestamp' => 12345,
            'player_steam_id' => 'STEAM_987654321',
            'player_side' => 'T',
            'grenade_type' => 'flashbang',
            'player_x' => 100.0,
            'player_y' => 200.0,
            'player_z' => 50.0,
            'player_aim_x' => 101.0,
            'player_aim_y' => 201.0,
            'player_aim_z' => 51.0,
            'grenade_final_x' => 150.0,
            'grenade_final_y' => 250.0,
            'grenade_final_z' => 75.0,
            'damage_dealt' => 0,
            'flash_duration' => 2.5,
            'friendly_flash_duration' => 0,
            'enemy_flash_duration' => 2.5,
            'friendly_players_affected' => 0,
            'enemy_players_affected' => 2,
            'throw_type' => 'pop',
            'effectiveness_rating' => 8.5,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/grenade-favourites', $favouriteData);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Grenade added to favourites successfully',
            ])
            ->assertJsonStructure([
                'favourite' => [
                    'id',
                    'match_id',
                    'user_id',
                    'round_number',
                    'match',
                ],
            ]);

        $this->assertDatabaseHas('grenade_favourites', [
            'user_id' => $this->user->id,
            'match_id' => $this->match->id,
            'round_number' => 1,
        ]);
    }

    public function test_create_prevents_duplicate_favourites(): void
    {
        $favouriteData = [
            'match_id' => $this->match->id,
            'round_number' => 1,
            'round_time' => 120.5,
            'tick_timestamp' => 12345,
            'player_steam_id' => 'STEAM_987654321',
            'player_side' => 'T',
            'grenade_type' => 'flashbang',
            'player_x' => 100.0,
            'player_y' => 200.0,
            'player_z' => 50.0,
            'player_aim_x' => 101.0,
            'player_aim_y' => 201.0,
            'player_aim_z' => 51.0,
            'grenade_final_x' => 150.0,
            'grenade_final_y' => 250.0,
            'grenade_final_z' => 75.0,
        ];

        // Create the first favourite
        $this->actingAs($this->user)
            ->postJson('/api/grenade-favourites', $favouriteData);

        // Try to create the same favourite again
        $response = $this->actingAs($this->user)
            ->postJson('/api/grenade-favourites', $favouriteData);

        $response->assertStatus(409)
            ->assertJson([
                'message' => 'Grenade already exists in favourites',
            ]);
    }

    public function test_create_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/grenade-favourites', []);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors',
            ]);
    }

    public function test_delete_removes_favourite(): void
    {
        $favourite = GrenadeFavourite::factory()->create([
            'user_id' => $this->user->id,
            'match_id' => $this->match->id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson('/api/grenade-favourites/' . $favourite->id);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Favourite removed successfully',
            ]);

        $this->assertDatabaseMissing('grenade_favourites', [
            'id' => $favourite->id,
        ]);
    }

    public function test_delete_returns_404_for_nonexistent_favourite(): void
    {
        $response = $this->actingAs($this->user)
            ->deleteJson('/api/grenade-favourites/999');

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Grenade favourite not found',
            ]);
    }

    public function test_delete_prevents_deleting_other_users_favourite(): void
    {
        $otherUser = User::factory()->create();
        $favourite = GrenadeFavourite::factory()->create([
            'user_id' => $otherUser->id,
            'match_id' => $this->match->id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson('/api/grenade-favourites/' . $favourite->id);

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Grenade favourite not found',
            ]);

        // Favourite should still exist
        $this->assertDatabaseHas('grenade_favourites', [
            'id' => $favourite->id,
        ]);
    }

    public function test_get_match_favourites_returns_favourite_keys_for_match(): void
    {
        // Create a player record for the steam_id that will be used in the factory
        $player = \App\Models\Player::factory()->create([
            'steam_id' => 'STEAM_123456789',
            'name' => 'Test Player',
        ]);

        // Create some favourites for the user in this match
        $favourite1 = GrenadeFavourite::factory()->create([
            'user_id' => $this->user->id,
            'match_id' => $this->match->id,
            'player_steam_id' => $player->steam_id,
            'round_number' => 1,
            'tick_timestamp' => 1000,
        ]);

        $favourite2 = GrenadeFavourite::factory()->create([
            'user_id' => $this->user->id,
            'match_id' => $this->match->id,
            'player_steam_id' => $player->steam_id,
            'round_number' => 2,
            'tick_timestamp' => 2000,
        ]);

        // Create a favourite for a different match (should not be included)
        $otherMatch = GameMatch::factory()->create(['map' => 'de_mirage']);
        GrenadeFavourite::factory()->create([
            'user_id' => $this->user->id,
            'match_id' => $otherMatch->id,
            'player_steam_id' => $player->steam_id,
            'round_number' => 1,
            'tick_timestamp' => 1000,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/matches/{$this->match->id}/grenade-favourites");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'favourite_keys' => [],
                'favourite_ids' => [],
            ]);

        $data = $response->json();

        // Should have 2 favourite keys
        $this->assertCount(2, $data['favourite_keys']);
        $this->assertCount(2, $data['favourite_ids']);

        // Check that the keys are in the correct format
        $expectedKey1 = "{$this->match->id}-1-1000-{$player->steam_id}";
        $expectedKey2 = "{$this->match->id}-2-2000-{$player->steam_id}";

        $this->assertContains($expectedKey1, $data['favourite_keys']);
        $this->assertContains($expectedKey2, $data['favourite_keys']);

        // Check that the favourite IDs are mapped correctly
        $this->assertEquals($favourite1->id, $data['favourite_ids'][$expectedKey1]);
        $this->assertEquals($favourite2->id, $data['favourite_ids'][$expectedKey2]);
    }

    public function test_get_match_favourites_returns_empty_for_match_with_no_favourites(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/matches/{$this->match->id}/grenade-favourites");

        $response->assertStatus(200)
            ->assertJson([
                'favourite_keys' => [],
                'favourite_ids' => [],
            ]);
    }
}
