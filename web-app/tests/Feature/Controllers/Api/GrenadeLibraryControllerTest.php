<?php

namespace Tests\Feature\Controllers\Api;

use App\Enums\GrenadeType;
use App\Enums\MatchType;
use App\Enums\Team;
use App\Models\GameMatch;
use App\Models\GrenadeEvent;
use App\Models\MatchPlayer;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrenadeLibraryControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Player $player;

    private GameMatch $match;

    private Player $otherPlayer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'steam_id' => '76561198012345678',
        ]);

        $this->player = Player::factory()->create([
            'steam_id' => '76561198012345678',
        ]);

        $this->otherPlayer = Player::factory()->create([
            'steam_id' => '76561198087654321',
            'name' => 'Other Player',
        ]);

        $this->match = GameMatch::factory()->create([
            'map' => 'de_mirage',
            'match_type' => MatchType::MATCHMAKING,
        ]);

        // Create match player relationship
        MatchPlayer::factory()->create([
            'match_id' => $this->match->id,
            'player_id' => $this->player->id,
            'team' => Team::TEAM_A,
        ]);
    }

    public function test_filter_options_returns_unauthorized_for_unauthenticated_user()
    {
        $response = $this->getJson('/api/grenade-library/filter-options');

        $response->assertStatus(401);
    }

    public function test_filter_options_returns_hardcoded_maps_and_grenade_types()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/grenade-library/filter-options');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'maps' => [
                    '*' => ['name', 'displayName'],
                ],
                'matches' => [],
                'rounds' => [],
                'grenadeTypes' => [
                    '*' => ['type', 'displayName'],
                ],
                'players' => [],
                'playerSides' => [
                    '*' => ['side', 'displayName'],
                ],
            ]);

        // Verify hardcoded maps
        $response->assertJson([
            'maps' => [
                ['name' => 'de_ancient', 'displayName' => 'Ancient'],
                ['name' => 'de_dust2', 'displayName' => 'Dust II'],
                ['name' => 'de_mirage', 'displayName' => 'Mirage'],
                ['name' => 'de_inferno', 'displayName' => 'Inferno'],
                ['name' => 'de_nuke', 'displayName' => 'Nuke'],
                ['name' => 'de_overpass', 'displayName' => 'Overpass'],
                ['name' => 'de_train', 'displayName' => 'Train'],
                ['name' => 'de_cache', 'displayName' => 'Cache'],
                ['name' => 'de_anubis', 'displayName' => 'Anubis'],
                ['name' => 'de_vertigo', 'displayName' => 'Vertigo'],
            ],
        ]);

        // Verify hardcoded grenade types with Fire Grenades
        $response->assertJson([
            'grenadeTypes' => [
                ['type' => 'fire_grenades', 'displayName' => 'Fire Grenades'],
                ['type' => GrenadeType::SMOKE_GRENADE->value, 'displayName' => 'Smoke Grenade'],
                ['type' => GrenadeType::HE_GRENADE->value, 'displayName' => 'HE Grenade'],
                ['type' => GrenadeType::FLASHBANG->value, 'displayName' => 'Flashbang'],
                ['type' => GrenadeType::DECOY->value, 'displayName' => 'Decoy Grenade'],
            ],
        ]);

        // Verify hardcoded player sides
        $response->assertJson([
            'playerSides' => [
                ['side' => 'CT', 'displayName' => 'Counter-Terrorist'],
                ['side' => 'T', 'displayName' => 'Terrorist'],
            ],
        ]);
    }

    public function test_filter_options_returns_matches_for_selected_map()
    {
        // Create another match on a different map
        $otherMatch = GameMatch::factory()->create([
            'map' => 'de_dust2',
            'match_type' => MatchType::MATCHMAKING,
        ]);

        MatchPlayer::factory()->create([
            'match_id' => $otherMatch->id,
            'player_id' => $this->player->id,
            'team' => Team::TEAM_A,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/grenade-library/filter-options?map=de_mirage');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'matches' => [
                    '*' => ['id', 'name'],
                ],
            ]);

        $response->assertJson([
            'matches' => [
                [
                    'id' => $this->match->id,
                    'name' => "Match #{$this->match->id} - de_mirage",
                ],
            ],
        ]);

        // Should not include the other match
        $response->assertJsonMissing([
            'matches' => [
                [
                    'id' => $otherMatch->id,
                    'name' => "Match #{$otherMatch->id} - de_dust2",
                ],
            ],
        ]);
    }

    public function test_filter_options_returns_rounds_for_selected_match()
    {
        // Create grenade events for different rounds
        GrenadeEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'round_number' => 1,
        ]);

        GrenadeEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'round_number' => 3,
        ]);

        GrenadeEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'round_number' => 5,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/grenade-library/filter-options?map=de_mirage&match_id={$this->match->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'rounds' => [
                    '*' => ['number'],
                ],
            ]);

        $response->assertJson([
            'rounds' => [
                ['number' => 1],
                ['number' => 3],
                ['number' => 5],
            ],
        ]);
    }

    public function test_filter_options_returns_players_for_selected_match()
    {
        // Add the other player to the match
        MatchPlayer::factory()->create([
            'match_id' => $this->match->id,
            'player_id' => $this->otherPlayer->id,
            'team' => Team::TEAM_B,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/grenade-library/filter-options?map=de_mirage&match_id={$this->match->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'players' => [
                    '*' => ['steam_id', 'name'],
                ],
            ]);

        $players = $response->json('players');
        $this->assertCount(2, $players);

        // Check that both players are present (order doesn't matter due to alphabetical sorting)
        $playerSteamIds = collect($players)->pluck('steam_id')->toArray();
        $this->assertContains($this->player->steam_id, $playerSteamIds);
        $this->assertContains($this->otherPlayer->steam_id, $playerSteamIds);

        // Check that both player names are present
        $playerNames = collect($players)->pluck('name')->toArray();
        $this->assertContains($this->player->name, $playerNames);
        $this->assertContains($this->otherPlayer->name, $playerNames);
    }

    public function test_index_returns_unauthorized_for_unauthenticated_user()
    {
        $response = $this->getJson('/api/grenade-library');

        $response->assertStatus(401);
    }

    public function test_index_returns_grenades_with_basic_filters()
    {
        // Create grenade events
        $smokeGrenade = GrenadeEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'grenade_type' => GrenadeType::SMOKE_GRENADE,
            'round_number' => 1,
        ]);

        $flashGrenade = GrenadeEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'grenade_type' => GrenadeType::FLASHBANG,
            'round_number' => 2,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/grenade-library?map=de_mirage&match_id={$this->match->id}&grenade_type=" . GrenadeType::SMOKE_GRENADE->value);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'grenades' => [
                    '*' => [
                        'id',
                        'match_id',
                        'player_steam_id',
                        'grenade_type',
                        'round_number',
                        'map',
                        'player_name',
                    ],
                ],
                'filters' => [
                    'map',
                    'match_id',
                    'round_number',
                    'grenade_type',
                    'player_steam_id',
                    'player_side',
                ],
            ]);

        $response->assertJson([
            'grenades' => [
                [
                    'id' => $smokeGrenade->id,
                    'match_id' => $this->match->id,
                    'player_steam_id' => $this->player->steam_id,
                    'grenade_type' => GrenadeType::SMOKE_GRENADE->value,
                    'round_number' => 1,
                    'map' => 'de_mirage',
                    'player_name' => $this->player->name,
                ],
            ],
            'filters' => [
                'map' => 'de_mirage',
                'match_id' => $this->match->id,
                'round_number' => null,
                'grenade_type' => GrenadeType::SMOKE_GRENADE->value,
                'player_steam_id' => null,
                'player_side' => null,
            ],
        ]);

        // Should not include the flash grenade
        $response->assertJsonMissing([
            'grenades' => [
                [
                    'id' => $flashGrenade->id,
                    'grenade_type' => GrenadeType::FLASHBANG->value,
                ],
            ],
        ]);
    }

    public function test_index_returns_fire_grenades_with_special_filter()
    {
        // Create different types of grenades
        $molotov = GrenadeEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'grenade_type' => GrenadeType::MOLOTOV,
        ]);

        $incendiary = GrenadeEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'grenade_type' => GrenadeType::INCENDIARY,
        ]);

        $smoke = GrenadeEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'grenade_type' => GrenadeType::SMOKE_GRENADE,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/grenade-library?map=de_mirage&match_id={$this->match->id}&grenade_type=fire_grenades");

        $response->assertStatus(200);

        $grenades = $response->json('grenades');
        $this->assertCount(2, $grenades);

        // Should include both molotov and incendiary
        $grenadeTypes = collect($grenades)->pluck('grenade_type')->toArray();
        $this->assertContains(GrenadeType::MOLOTOV->value, $grenadeTypes);
        $this->assertContains(GrenadeType::INCENDIARY->value, $grenadeTypes);

        // Should not include smoke
        $this->assertNotContains(GrenadeType::SMOKE_GRENADE->value, $grenadeTypes);
    }

    public function test_index_filters_by_round_number()
    {
        // Create grenade events for different rounds
        $round1Grenade = GrenadeEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'grenade_type' => GrenadeType::SMOKE_GRENADE,
            'round_number' => 1,
        ]);

        $round2Grenade = GrenadeEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'grenade_type' => GrenadeType::SMOKE_GRENADE,
            'round_number' => 2,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/grenade-library?map=de_mirage&match_id={$this->match->id}&grenade_type=" . GrenadeType::SMOKE_GRENADE->value . '&round_number=1');

        $response->assertStatus(200);

        $grenades = $response->json('grenades');
        $this->assertCount(1, $grenades);
        $this->assertEquals($round1Grenade->id, $grenades[0]['id']);
    }

    public function test_index_filters_by_player_steam_id()
    {
        // Create grenade events for different players
        $userGrenade = GrenadeEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'grenade_type' => GrenadeType::SMOKE_GRENADE,
        ]);

        $otherGrenade = GrenadeEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->otherPlayer->steam_id,
            'grenade_type' => GrenadeType::SMOKE_GRENADE,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/grenade-library?map=de_mirage&match_id={$this->match->id}&grenade_type=" . GrenadeType::SMOKE_GRENADE->value . "&player_steam_id={$this->player->steam_id}");

        $response->assertStatus(200);

        $grenades = $response->json('grenades');
        $this->assertCount(1, $grenades);
        $this->assertEquals($userGrenade->id, $grenades[0]['id']);
    }

    public function test_index_filters_by_player_side()
    {
        // Create grenade events for different player sides
        $ctGrenade = GrenadeEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'grenade_type' => GrenadeType::SMOKE_GRENADE,
            'player_side' => 'CT',
        ]);

        $tGrenade = GrenadeEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'grenade_type' => GrenadeType::SMOKE_GRENADE,
            'player_side' => 'T',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/grenade-library?map=de_mirage&match_id={$this->match->id}&grenade_type=" . GrenadeType::SMOKE_GRENADE->value . "&player_side=CT");

        $response->assertStatus(200);

        $grenades = $response->json('grenades');
        $this->assertCount(1, $grenades);
        $this->assertEquals($ctGrenade->id, $grenades[0]['id']);
    }

    public function test_index_returns_all_rounds_when_round_number_is_all()
    {
        // Create grenade events for different rounds
        GrenadeEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'grenade_type' => GrenadeType::SMOKE_GRENADE,
            'round_number' => 1,
        ]);

        GrenadeEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'grenade_type' => GrenadeType::SMOKE_GRENADE,
            'round_number' => 2,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/grenade-library?map=de_mirage&match_id={$this->match->id}&grenade_type=" . GrenadeType::SMOKE_GRENADE->value . '&round_number=all');

        $response->assertStatus(200);

        $grenades = $response->json('grenades');
        $this->assertCount(2, $grenades);
    }

    public function test_index_returns_all_players_when_player_steam_id_is_all()
    {
        // Create grenade events for different players
        GrenadeEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'grenade_type' => GrenadeType::SMOKE_GRENADE,
        ]);

        GrenadeEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->otherPlayer->steam_id,
            'grenade_type' => GrenadeType::SMOKE_GRENADE,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/grenade-library?map=de_mirage&match_id={$this->match->id}&grenade_type=" . GrenadeType::SMOKE_GRENADE->value . '&player_steam_id=all');

        $response->assertStatus(200);

        $grenades = $response->json('grenades');
        $this->assertCount(2, $grenades);
    }

    public function test_index_only_returns_grenades_from_user_matches()
    {
        // Create a match that the user is not part of
        $otherMatch = GameMatch::factory()->create([
            'map' => 'de_mirage',
        ]);

        // Create grenade events for both matches
        $userGrenade = GrenadeEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'grenade_type' => GrenadeType::SMOKE_GRENADE,
        ]);

        $otherGrenade = GrenadeEvent::factory()->create([
            'match_id' => $otherMatch->id,
            'player_steam_id' => $this->player->steam_id,
            'grenade_type' => GrenadeType::SMOKE_GRENADE,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/grenade-library?map=de_mirage&match_id={$this->match->id}&grenade_type=" . GrenadeType::SMOKE_GRENADE->value);

        $response->assertStatus(200);

        $grenades = $response->json('grenades');
        $this->assertCount(1, $grenades);
        $this->assertEquals($userGrenade->id, $grenades[0]['id']);

        // Should not include grenades from matches the user is not part of
        $this->assertNotContains($otherGrenade->id, collect($grenades)->pluck('id')->toArray());
    }

    public function test_filter_options_only_returns_matches_user_participated_in()
    {
        // Create a match that the user is not part of
        $otherMatch = GameMatch::factory()->create([
            'map' => 'de_mirage',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/grenade-library/filter-options?map=de_mirage');

        $response->assertStatus(200);

        $matches = $response->json('matches');
        $this->assertCount(1, $matches);
        $this->assertEquals($this->match->id, $matches[0]['id']);

        // Should not include matches the user is not part of
        $matchIds = collect($matches)->pluck('id')->toArray();
        $this->assertNotContains($otherMatch->id, $matchIds);
    }

    public function test_filter_options_returns_empty_arrays_when_no_data()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/grenade-library/filter-options?map=de_dust2');

        $response->assertStatus(200)
            ->assertJson([
                'matches' => [],
                'rounds' => [],
                'players' => [],
            ]);
    }

    public function test_index_returns_empty_array_when_no_matches()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/grenade-library?map=de_dust2&match_id=999&grenade_type=' . GrenadeType::SMOKE_GRENADE->value);

        $response->assertStatus(200)
            ->assertJson([
                'grenades' => [],
            ]);
    }

    public function test_index_handles_missing_optional_filters()
    {
        // Create grenade events
        GrenadeEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'grenade_type' => GrenadeType::SMOKE_GRENADE,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/grenade-library?map=de_mirage&match_id={$this->match->id}&grenade_type=" . GrenadeType::SMOKE_GRENADE->value);

        $response->assertStatus(200);

        $grenades = $response->json('grenades');
        $this->assertCount(1, $grenades);
    }

    public function test_index_requires_authentication()
    {
        $response = $this->getJson('/api/grenade-library');

        $response->assertStatus(401);
    }

    public function test_filter_options_requires_authentication()
    {
        $response = $this->getJson('/api/grenade-library/filter-options');

        $response->assertStatus(401);
    }

    public function test_index_returns_correct_filter_values_in_response()
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/grenade-library?map=de_mirage&match_id={$this->match->id}&grenade_type=" . GrenadeType::SMOKE_GRENADE->value . "&round_number=1&player_steam_id={$this->player->steam_id}");

        $response->assertStatus(200)
            ->assertJson([
                'filters' => [
                    'map' => 'de_mirage',
                    'match_id' => $this->match->id,
                    'round_number' => '1',
                    'grenade_type' => GrenadeType::SMOKE_GRENADE->value,
                    'player_steam_id' => $this->player->steam_id,
                    'player_side' => null,
                ],
            ]);
    }
}
