<?php

namespace Tests\Feature\Services;

use App\Models\GameMatch;
use App\Models\Player;
use App\Models\PlayerMatchEvent;
use App\Models\User;
use App\Services\UtilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class UtilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private UtilityService $service;

    private User $user;

    private Player $player;

    private GameMatch $match1;

    private GameMatch $match2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new UtilityService;

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
        ]);

        $this->match2 = GameMatch::factory()->create([
            'map' => 'de_mirage',
            'winning_team' => 'B',
        ]);

        // Attach player to matches
        $this->match1->players()->attach($this->player->id, ['team' => 'A']);
        $this->match2->players()->attach($this->player->id, ['team' => 'B']);
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
    public function it_caches_utility_stats_results()
    {
        Cache::flush();
        $this->createPlayerMatchEvents();

        $filters = ['past_match_count' => 10];

        // First call should query database
        $result1 = $this->service->getUtilityStats($this->user, $filters);

        // Second call should use cache
        $result2 = $this->service->getUtilityStats($this->user, $filters);

        $this->assertEquals($result1, $result2);
    }

    /**
     * Helper method to create player match events
     */
    private function createPlayerMatchEvents(): void
    {
        PlayerMatchEvent::factory()->create([
            'match_id' => $this->match1->id,
            'player_steam_id' => $this->player->steam_id,
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
        ]);

        PlayerMatchEvent::factory()->create([
            'match_id' => $this->match2->id,
            'player_steam_id' => $this->player->steam_id,
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
        ]);
    }
}
