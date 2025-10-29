<?php

namespace Tests\Unit\Services;

use App\Models\GameMatch;
use App\Models\Player;
use App\Models\PlayerMatchAimEvent;
use App\Models\PlayerMatchAimWeaponEvent;
use App\Models\User;
use App\Services\Matches\AimTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AimTrackingServiceTest extends TestCase
{
    use RefreshDatabase;

    private AimTrackingService $service;

    private User $user;

    private Player $player;

    private GameMatch $match;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new AimTrackingService;

        $this->user = User::factory()->create([
            'steam_id' => '76561198012345678',
        ]);

        $this->player = Player::factory()->create([
            'steam_id' => '76561198012345678',
            'name' => 'TestPlayer',
        ]);

        $this->match = GameMatch::factory()->create([
            'map' => 'de_dust2',
            'winning_team_score' => 16,
            'losing_team_score' => 14,
        ]);

        $this->match->players()->attach($this->player->id, ['team' => 'A']);
    }

    public function test_get_returns_empty_array_when_no_match_found()
    {
        $filters = ['player_steam_id' => $this->player->steam_id];

        $result = $this->service->get($this->user, $filters, 99999);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_get_returns_empty_array_when_no_player_steam_id_provided()
    {
        $filters = [];

        $result = $this->service->get($this->user, $filters, $this->match->id);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_get_returns_empty_array_when_no_aim_data_found()
    {
        $filters = ['player_steam_id' => $this->player->steam_id];

        $result = $this->service->get($this->user, $filters, $this->match->id);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_get_returns_correct_structure_with_aim_data()
    {
        $aimEvent = PlayerMatchAimEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'shots_fired' => 100,
            'shots_hit' => 45,
            'accuracy_all_shots' => 45.00,
            'spraying_shots_fired' => 40,
            'spraying_shots_hit' => 15,
            'spraying_accuracy' => 37.50,
            'average_crosshair_placement_x' => 2.5,
            'average_crosshair_placement_y' => 3.2,
            'headshot_accuracy' => 28.50,
            'average_time_to_damage' => 0.85,
            'head_hits_total' => 12,
            'upper_chest_hits_total' => 8,
            'chest_hits_total' => 15,
            'legs_hits_total' => 10,
            'aim_rating' => 75.50,
        ]);

        $filters = ['player_steam_id' => $this->player->steam_id];

        $result = $this->service->get($this->user, $filters, $this->match->id);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('match_id', $result);
        $this->assertArrayHasKey('player_steam_id', $result);
        $this->assertArrayHasKey('shots_fired', $result);
        $this->assertArrayHasKey('shots_hit', $result);
        $this->assertArrayHasKey('accuracy_all_shots', $result);
        $this->assertArrayHasKey('spraying_shots_fired', $result);
        $this->assertArrayHasKey('spraying_shots_hit', $result);
        $this->assertArrayHasKey('spraying_accuracy', $result);
        $this->assertArrayHasKey('average_crosshair_placement_x', $result);
        $this->assertArrayHasKey('average_crosshair_placement_y', $result);
        $this->assertArrayHasKey('headshot_accuracy', $result);
        $this->assertArrayHasKey('average_time_to_damage', $result);
        $this->assertArrayHasKey('head_hits_total', $result);
        $this->assertArrayHasKey('upper_chest_hits_total', $result);
        $this->assertArrayHasKey('chest_hits_total', $result);
        $this->assertArrayHasKey('legs_hits_total', $result);
        $this->assertArrayHasKey('aim_rating', $result);

        $this->assertEquals($this->match->id, $result['match_id']);
        $this->assertEquals($this->player->steam_id, $result['player_steam_id']);
        $this->assertEquals(100, $result['shots_fired']);
        $this->assertEquals(45, $result['shots_hit']);
        $this->assertEquals(45.00, $result['accuracy_all_shots']);
        $this->assertEquals(75.50, $result['aim_rating']);
    }

    public function test_get_weapon_stats_returns_empty_array_when_no_match_found()
    {
        $filters = ['player_steam_id' => $this->player->steam_id];

        $result = $this->service->getWeaponStats($this->user, $filters, 99999);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_get_weapon_stats_returns_empty_array_when_no_player_steam_id_provided()
    {
        $filters = [];

        $result = $this->service->getWeaponStats($this->user, $filters, $this->match->id);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_get_weapon_stats_returns_aggregated_data_when_no_weapon_specified()
    {
        PlayerMatchAimEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'shots_fired' => 150,
            'shots_hit' => 65,
            'accuracy_all_shots' => 43.33,
        ]);

        $filters = ['player_steam_id' => $this->player->steam_id];

        $result = $this->service->getWeaponStats($this->user, $filters, $this->match->id);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('match_id', $result);
        $this->assertArrayHasKey('player_steam_id', $result);
        $this->assertArrayHasKey('weapon_name', $result);
        $this->assertArrayHasKey('shots_fired', $result);
        $this->assertArrayHasKey('shots_hit', $result);
        $this->assertArrayHasKey('accuracy_all_shots', $result);

        $this->assertEquals($this->match->id, $result['match_id']);
        $this->assertEquals($this->player->steam_id, $result['player_steam_id']);
        $this->assertNull($result['weapon_name']);
        $this->assertEquals(150, $result['shots_fired']);
        $this->assertEquals(65, $result['shots_hit']);
    }

    public function test_get_weapon_stats_returns_specific_weapon_data()
    {
        PlayerMatchAimWeaponEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'weapon_name' => 'ak47',
            'shots_fired' => 80,
            'shots_hit' => 35,
            'accuracy_all_shots' => 43.75,
        ]);

        $filters = [
            'player_steam_id' => $this->player->steam_id,
            'weapon_name' => 'ak47',
        ];

        $result = $this->service->getWeaponStats($this->user, $filters, $this->match->id);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('weapon_name', $result);
        $this->assertEquals('ak47', $result['weapon_name']);
        $this->assertEquals(80, $result['shots_fired']);
        $this->assertEquals(35, $result['shots_hit']);
        $this->assertEquals(43.75, $result['accuracy_all_shots']);
    }

    public function test_get_weapon_stats_returns_empty_array_when_weapon_not_found()
    {
        $filters = [
            'player_steam_id' => $this->player->steam_id,
            'weapon_name' => 'awp',
        ];

        $result = $this->service->getWeaponStats($this->user, $filters, $this->match->id);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_get_filter_options_returns_empty_array_when_no_match_found()
    {
        $filters = [];

        $result = $this->service->getFilterOptions($this->user, $filters, 99999);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_get_filter_options_returns_players_list()
    {
        $player2 = Player::factory()->create([
            'steam_id' => '76561198087654321',
            'name' => 'TestPlayer2',
        ]);

        $this->match->players()->attach($player2->id, ['team' => 'B']);

        $filters = [];

        $result = $this->service->getFilterOptions($this->user, $filters, $this->match->id);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('players', $result);
        $this->assertArrayHasKey('weapons', $result);
        $this->assertArrayHasKey('current_user_steam_id', $result);

        $this->assertCount(2, $result['players']);
        $this->assertEquals($this->user->steam_id, $result['current_user_steam_id']);

        // Check player structure
        $playerIds = collect($result['players'])->pluck('steam_id')->toArray();
        $this->assertContains($this->player->steam_id, $playerIds);
        $this->assertContains($player2->steam_id, $playerIds);
    }

    public function test_get_filter_options_returns_weapons_for_player()
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

        PlayerMatchAimWeaponEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'weapon_name' => 'm4a1',
        ]);

        $filters = ['player_steam_id' => $this->player->steam_id];

        $result = $this->service->getFilterOptions($this->user, $filters, $this->match->id);

        $this->assertArrayHasKey('weapons', $result);
        $this->assertCount(4, $result['weapons']); // 3 weapons + "All Weapons" option

        // Check "All Weapons" is first
        $this->assertEquals('all', $result['weapons'][0]['value']);
        $this->assertEquals('All Weapons', $result['weapons'][0]['label']);

        // Check weapon structure
        $weaponValues = collect($result['weapons'])->pluck('value')->toArray();
        $this->assertContains('ak47', $weaponValues);
        $this->assertContains('awp', $weaponValues);
        $this->assertContains('m4a1', $weaponValues);
    }

    public function test_get_filter_options_returns_empty_weapons_when_no_player_selected()
    {
        $filters = [];

        $result = $this->service->getFilterOptions($this->user, $filters, $this->match->id);

        $this->assertArrayHasKey('weapons', $result);
        $this->assertEmpty($result['weapons']);
    }

    public function test_weapon_display_names_are_formatted_correctly()
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

        $filters = ['player_steam_id' => $this->player->steam_id];

        $result = $this->service->getFilterOptions($this->user, $filters, $this->match->id);

        $weapons = collect($result['weapons']);

        // Find AK47
        $ak47 = $weapons->firstWhere('value', 'ak47');
        $this->assertEquals('AK-47', $ak47['label']);

        // Find USP-S
        $usp = $weapons->firstWhere('value', 'usp_silencer');
        $this->assertEquals('USP-S', $usp['label']);
    }

    public function test_cache_key_generation_is_consistent()
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
        $this->assertStringStartsWith('aim-tracking_', $key1);
    }

    public function test_weapon_cache_key_generation_includes_weapon_filter()
    {
        $filters1 = ['player_steam_id' => '76561198012345678', 'weapon_name' => 'ak47'];
        $filters2 = ['player_steam_id' => '76561198012345678', 'weapon_name' => 'awp'];

        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getWeaponCacheKey');
        $method->setAccessible(true);

        $key1 = $method->invoke($this->service, $filters1);
        $key2 = $method->invoke($this->service, $filters2);

        // Different weapon filters should generate different keys
        $this->assertNotEquals($key1, $key2);

        // Key should start with expected prefix
        $this->assertStringStartsWith('aim-tracking-weapon_', $key1);
    }

    public function test_get_weapon_stats_includes_all_hit_region_data()
    {
        PlayerMatchAimWeaponEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'weapon_name' => 'ak47',
            'head_hits_total' => 15,
            'upper_chest_hits_total' => 10,
            'chest_hits_total' => 20,
            'legs_hits_total' => 8,
        ]);

        $filters = [
            'player_steam_id' => $this->player->steam_id,
            'weapon_name' => 'ak47',
        ];

        $result = $this->service->getWeaponStats($this->user, $filters, $this->match->id);

        $this->assertArrayHasKey('head_hits_total', $result);
        $this->assertArrayHasKey('upper_chest_hits_total', $result);
        $this->assertArrayHasKey('chest_hits_total', $result);
        $this->assertArrayHasKey('legs_hits_total', $result);

        $this->assertEquals(15, $result['head_hits_total']);
        $this->assertEquals(10, $result['upper_chest_hits_total']);
        $this->assertEquals(20, $result['chest_hits_total']);
        $this->assertEquals(8, $result['legs_hits_total']);
    }

    public function test_get_includes_crosshair_placement_data()
    {
        PlayerMatchAimEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'average_crosshair_placement_x' => 1.234,
            'average_crosshair_placement_y' => -2.567,
        ]);

        $filters = ['player_steam_id' => $this->player->steam_id];

        $result = $this->service->get($this->user, $filters, $this->match->id);

        $this->assertArrayHasKey('average_crosshair_placement_x', $result);
        $this->assertArrayHasKey('average_crosshair_placement_y', $result);
        $this->assertEquals(1.234, $result['average_crosshair_placement_x']);
        $this->assertEquals(-2.567, $result['average_crosshair_placement_y']);
    }
}
