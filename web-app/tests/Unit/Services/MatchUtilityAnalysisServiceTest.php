<?php

namespace Tests\Unit\Services;

use App\Enums\GrenadeType;
use App\Models\GameMatch;
use App\Models\GrenadeEvent;
use App\Models\Player;
use App\Models\User;
use App\Services\MatchUtilityAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchUtilityAnalysisServiceTest extends TestCase
{
    use RefreshDatabase;

    private MatchUtilityAnalysisService $service;

    private User $user;

    private Player $player;

    private GameMatch $match;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new MatchUtilityAnalysisService;

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

    public function test_get_utility_analysis_returns_empty_array_for_invalid_user()
    {
        $userWithoutPlayer = User::factory()->create(['steam_id' => null]);

        $result = $this->service->getUtilityAnalysis($userWithoutPlayer, $this->match->id);

        $this->assertEmpty($result);
    }

    public function test_get_utility_analysis_returns_empty_array_for_nonexistent_match()
    {
        $result = $this->service->getUtilityAnalysis($this->user, 99999);

        $this->assertEmpty($result);
    }

    public function test_get_utility_analysis_returns_correct_structure()
    {
        GrenadeEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'grenade_type' => GrenadeType::FLASHBANG,
            'round_number' => 1,
            'effectiveness_rating' => 8,
            'enemy_flash_duration' => 2.5,
            'enemy_players_affected' => 2,
        ]);

        $result = $this->service->getUtilityAnalysis($this->user, $this->match->id);

        $this->assertArrayHasKey('utility_usage', $result);
        $this->assertArrayHasKey('grenade_effectiveness', $result);
        $this->assertArrayHasKey('grenade_timing', $result);
        $this->assertArrayHasKey('overall_stats', $result);
        $this->assertArrayHasKey('players', $result);
        $this->assertArrayHasKey('rounds', $result);
        $this->assertArrayHasKey('current_user_steam_id', $result);
        $this->assertEquals($this->user->steam_id, $result['current_user_steam_id']);
    }

    public function test_utility_usage_calculation()
    {
        GrenadeEvent::factory()->count(3)->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'grenade_type' => GrenadeType::FLASHBANG,
        ]);

        GrenadeEvent::factory()->count(2)->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'grenade_type' => GrenadeType::HE_GRENADE,
        ]);

        $result = $this->service->getUtilityAnalysis($this->user, $this->match->id);

        $this->assertCount(2, $result['utility_usage']);

        $flashbangUsage = collect($result['utility_usage'])->firstWhere('type', GrenadeType::FLASHBANG->value);
        $this->assertEquals(3, $flashbangUsage['count']);
        $this->assertEquals(60.0, $flashbangUsage['percentage']);

        $heUsage = collect($result['utility_usage'])->firstWhere('type', GrenadeType::HE_GRENADE->value);
        $this->assertEquals(2, $heUsage['count']);
        $this->assertEquals(40.0, $heUsage['percentage']);
    }

    public function test_grenade_effectiveness_by_round()
    {
        GrenadeEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'round_number' => 1,
            'effectiveness_rating' => 8,
        ]);

        GrenadeEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'round_number' => 1,
            'effectiveness_rating' => 6,
        ]);

        GrenadeEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'round_number' => 2,
            'effectiveness_rating' => 9,
        ]);

        $result = $this->service->getUtilityAnalysis($this->user, $this->match->id);

        $this->assertCount(2, $result['grenade_effectiveness']);

        $round1 = collect($result['grenade_effectiveness'])->firstWhere('round', 1);
        $this->assertEquals(7.0, $round1['effectiveness']);
        $this->assertEquals(2, $round1['total_grenades']);

        $round2 = collect($result['grenade_effectiveness'])->firstWhere('round', 2);
        $this->assertEquals(9.0, $round2['effectiveness']);
        $this->assertEquals(1, $round2['total_grenades']);
    }

    public function test_overall_grenade_rating_calculation()
    {
        GrenadeEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'effectiveness_rating' => 8,
        ]);

        GrenadeEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'effectiveness_rating' => 6,
        ]);

        GrenadeEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'effectiveness_rating' => null,
        ]);

        $result = $this->service->getUtilityAnalysis($this->user, $this->match->id);

        $this->assertEquals(7.0, $result['overall_stats']['overall_grenade_rating']);
    }

    public function test_flash_stats_calculation()
    {
        GrenadeEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'grenade_type' => GrenadeType::FLASHBANG,
            'enemy_flash_duration' => 2.0,
            'friendly_flash_duration' => 1.0,
            'enemy_players_affected' => 2,
            'friendly_players_affected' => 1,
        ]);

        GrenadeEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'grenade_type' => GrenadeType::FLASHBANG,
            'enemy_flash_duration' => 3.0,
            'friendly_flash_duration' => 0.5,
            'enemy_players_affected' => 1,
            'friendly_players_affected' => 0,
        ]);

        $result = $this->service->getUtilityAnalysis($this->user, $this->match->id);

        $flashStats = $result['overall_stats']['flash_stats'];
        $this->assertEquals(2.5, $flashStats['enemy_avg_duration']);
        $this->assertEquals(0.75, $flashStats['friendly_avg_duration']);
        $this->assertEquals(1.5, $flashStats['enemy_avg_blinded']);
        $this->assertEquals(0.5, $flashStats['friendly_avg_blinded']);
    }

    public function test_he_stats_calculation()
    {
        GrenadeEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'grenade_type' => GrenadeType::HE_GRENADE,
            'damage_dealt' => 50,
        ]);

        GrenadeEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'grenade_type' => GrenadeType::HE_GRENADE,
            'damage_dealt' => 30,
        ]);

        GrenadeEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'grenade_type' => GrenadeType::HE_GRENADE,
            'damage_dealt' => 0,
        ]);

        $result = $this->service->getUtilityAnalysis($this->user, $this->match->id);

        $this->assertEquals(40.0, $result['overall_stats']['he_stats']['avg_damage']);
    }

    public function test_filters_by_player_steam_id()
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

        $result = $this->service->getUtilityAnalysis(
            $this->user,
            $this->match->id,
            $this->player->steam_id
        );

        $this->assertCount(1, $result['utility_usage']);
        $this->assertEquals(GrenadeType::FLASHBANG->value, $result['utility_usage'][0]['type']);
    }

    public function test_filters_by_round_number()
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

        $result = $this->service->getUtilityAnalysis(
            $this->user,
            $this->match->id,
            null,
            1
        );

        $this->assertCount(1, $result['utility_usage']);
        $this->assertEquals(GrenadeType::FLASHBANG->value, $result['utility_usage'][0]['type']);
    }

    public function test_fire_grenades_combination()
    {
        // Create Molotov and Incendiary grenades
        GrenadeEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'grenade_type' => GrenadeType::MOLOTOV,
            'round_number' => 1,
        ]);

        GrenadeEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'grenade_type' => GrenadeType::INCENDIARY,
            'round_number' => 2,
        ]);

        $result = $this->service->getUtilityAnalysis($this->user, $this->match->id);

        // Check utility usage combines into "Fire"
        $fireUsage = collect($result['utility_usage'])->firstWhere('type', 'Fire');
        $this->assertNotNull($fireUsage);
        $this->assertEquals(2, $fireUsage['count']);
        $this->assertEquals(100.0, $fireUsage['percentage']);

        // Check grenade timing also combines into "Fire"
        $fireTiming = collect($result['grenade_timing'])->firstWhere('type', 'Fire');
        $this->assertNotNull($fireTiming);
        $this->assertCount(2, $fireTiming['timing_data']);
    }
}
