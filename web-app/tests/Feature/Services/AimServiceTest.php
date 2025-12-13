<?php

namespace Tests\Feature\Services;

use App\Models\GameMatch;
use App\Models\Player;
use App\Models\PlayerMatchAimEvent;
use App\Models\PlayerMatchEvent;
use App\Models\User;
use App\Services\Analytics\AimService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AimServiceTest extends TestCase
{
    use RefreshDatabase;

    private AimService $service;

    private User $user;

    private Player $player;

    private GameMatch $match1;

    private GameMatch $match2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new AimService;

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
            'winning_team_score' => 16,
            'losing_team_score' => 14,
        ]);

        $this->match2 = GameMatch::factory()->create([
            'map' => 'de_mirage',
            'winning_team' => 'B',
            'winning_team_score' => 16,
            'losing_team_score' => 12,
        ]);

        // Attach player to matches
        $this->match1->players()->attach($this->player->id, ['team' => 'A']);
        $this->match2->players()->attach($this->player->id, ['team' => 'B']);
    }

    /** @test */
    public function it_returns_aim_stats_with_correct_structure()
    {
        $this->createPlayerMatchEvents();
        $this->createAimEvents();

        $filters = ['past_match_count' => 10];
        $result = $this->service->getAimStats($this->user, $filters);

        $this->assertArrayHasKey('aim_statistics', $result);
        $this->assertArrayHasKey('weapon_breakdown', $result);

        $aimStats = $result['aim_statistics'];
        $this->assertArrayHasKey('average_aim_rating', $aimStats);
        $this->assertArrayHasKey('average_headshot_percentage', $aimStats);
        $this->assertArrayHasKey('average_spray_accuracy', $aimStats);
        $this->assertArrayHasKey('average_crosshair_placement', $aimStats);
        $this->assertArrayHasKey('average_time_to_damage', $aimStats);

        // Each stat should have value, trend, and change
        foreach ($aimStats as $stat) {
            $this->assertArrayHasKey('value', $stat);
            $this->assertArrayHasKey('trend', $stat);
            $this->assertArrayHasKey('change', $stat);
        }
    }

    /** @test */
    public function it_calculates_crosshair_placement_using_pythagorean_theorem()
    {
        $this->createPlayerMatchEvents();

        // Create aim events with specific x and y values
        PlayerMatchAimEvent::factory()->create([
            'match_id' => $this->match1->id,
            'player_steam_id' => $this->player->steam_id,
            'average_crosshair_placement_x' => 3.0,
            'average_crosshair_placement_y' => 4.0,
            'aim_rating' => 80,
            'headshot_accuracy' => 50,
            'spraying_accuracy' => 60,
            'average_time_to_damage' => 0.5,
        ]);

        $filters = ['past_match_count' => 10];
        $result = $this->service->getAimStats($this->user, $filters);

        // sqrt(3^2 + 4^2) = sqrt(9 + 16) = sqrt(25) = 5.0
        $crosshairPlacement = $result['aim_statistics']['average_crosshair_placement']['value'];
        $this->assertEquals(5.0, $crosshairPlacement);
    }

    /** @test */
    public function it_caches_aim_stats_results()
    {
        Cache::flush();
        $this->createPlayerMatchEvents();
        $this->createAimEvents();

        $filters = ['past_match_count' => 10];

        // First call should query database
        $result1 = $this->service->getAimStats($this->user, $filters);

        // Second call should use cache
        $result2 = $this->service->getAimStats($this->user, $filters);

        $this->assertEquals($result1, $result2);
    }

    /** @test */
    public function it_returns_empty_aim_stats_when_user_has_no_steam_id()
    {
        $user = User::factory()->create(['steam_id' => null]);
        $filters = [];

        $result = $this->service->getAimStats($user, $filters);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('aim_statistics', $result);
        $this->assertEquals(0, $result['aim_statistics']['average_aim_rating']['value']);
    }

    /**
     * Helper method to create player match events
     */
    private function createPlayerMatchEvents(): void
    {
        PlayerMatchEvent::factory()->create([
            'match_id' => $this->match1->id,
            'player_steam_id' => $this->player->steam_id,
            'kills' => 20,
            'deaths' => 15,
        ]);

        PlayerMatchEvent::factory()->create([
            'match_id' => $this->match2->id,
            'player_steam_id' => $this->player->steam_id,
            'kills' => 20,
            'deaths' => 15,
        ]);
    }

    /**
     * Helper method to create aim events
     */
    private function createAimEvents(): void
    {
        PlayerMatchAimEvent::factory()->create([
            'match_id' => $this->match1->id,
            'player_steam_id' => $this->player->steam_id,
            'aim_rating' => 80,
            'headshot_accuracy' => 50,
            'spraying_accuracy' => 60,
            'average_crosshair_placement_x' => 2.5,
            'average_crosshair_placement_y' => 3.0,
            'average_time_to_damage' => 0.5,
        ]);

        PlayerMatchAimEvent::factory()->create([
            'match_id' => $this->match2->id,
            'player_steam_id' => $this->player->steam_id,
            'aim_rating' => 75,
            'headshot_accuracy' => 48,
            'spraying_accuracy' => 55,
            'average_crosshair_placement_x' => 2.0,
            'average_crosshair_placement_y' => 2.5,
            'average_time_to_damage' => 0.6,
        ]);
    }
}
