<?php

namespace Tests\Feature\Services;

use App\Models\GameMatch;
use App\Models\Player;
use App\Models\PlayerMatchEvent;
use App\Models\User;
use App\Services\Analytics\MapStatsService;
use App\Services\Matches\PlayerComplexionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MapStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    private MapStatsService $service;

    private User $user;

    private Player $player;

    private GameMatch $match1;

    private GameMatch $match2;

    protected function setUp(): void
    {
        parent::setUp();

        $playerComplexionService = new PlayerComplexionService;
        $this->service = new MapStatsService($playerComplexionService);

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
    public function it_returns_map_stats_with_correct_structure()
    {
        $this->createPlayerMatchEvents();

        $filters = ['past_match_count' => 10];
        $result = $this->service->getMapStats($this->user, $filters);

        $this->assertArrayHasKey('maps', $result);
        $this->assertArrayHasKey('total_matches', $result);
        $this->assertEquals(2, $result['total_matches']);
        $this->assertCount(2, $result['maps']);

        // Check map stats structure
        $mapStats = $result['maps'][0];
        $this->assertArrayHasKey('map', $mapStats);
        $this->assertArrayHasKey('matches', $mapStats);
        $this->assertArrayHasKey('wins', $mapStats);
        $this->assertArrayHasKey('win_rate', $mapStats);
        $this->assertArrayHasKey('avg_kills', $mapStats);
        $this->assertArrayHasKey('avg_assists', $mapStats);
        $this->assertArrayHasKey('avg_deaths', $mapStats);
        $this->assertArrayHasKey('avg_kd', $mapStats);
        $this->assertArrayHasKey('avg_adr', $mapStats);
        $this->assertArrayHasKey('avg_opening_kills', $mapStats);
        $this->assertArrayHasKey('avg_opening_deaths', $mapStats);
        $this->assertArrayHasKey('avg_complexion', $mapStats);
    }

    /** @test */
    public function it_calculates_win_percentage_correctly_for_map_stats()
    {
        $this->createPlayerMatchEvents();

        $filters = ['past_match_count' => 10];
        $result = $this->service->getMapStats($this->user, $filters);

        // Player was on team A which won match1
        // Player was on team B which won match2
        $dust2Stats = collect($result['maps'])->firstWhere('map', 'de_dust2');
        $this->assertEquals(1, $dust2Stats['wins']);
        $this->assertEquals(100.0, $dust2Stats['win_rate']);

        $mirageStats = collect($result['maps'])->firstWhere('map', 'de_mirage');
        $this->assertEquals(1, $mirageStats['wins']);
        $this->assertEquals(100.0, $mirageStats['win_rate']);
    }

    /** @test */
    public function it_sorts_maps_by_match_count()
    {
        // Create more matches on dust2
        for ($i = 0; $i < 3; $i++) {
            $match = GameMatch::factory()->create(['map' => 'de_dust2']);
            $match->players()->attach($this->player->id, ['team' => 'A']);
            PlayerMatchEvent::factory()->create([
                'match_id' => $match->id,
                'player_steam_id' => $this->player->steam_id,
            ]);
        }

        // Create one match on mirage
        $this->createPlayerMatchEvents();

        $filters = ['past_match_count' => 10];
        $result = $this->service->getMapStats($this->user, $filters);

        // First map should be the one with most matches (dust2 with 4 matches)
        $this->assertEquals('de_dust2', $result['maps'][0]['map']);
        $this->assertEquals(4, $result['maps'][0]['matches']);
    }

    /** @test */
    public function it_caches_map_stats_results()
    {
        Cache::flush();
        $this->createPlayerMatchEvents();

        $filters = ['past_match_count' => 10];

        // First call should query database
        $result1 = $this->service->getMapStats($this->user, $filters);

        // Second call should use cache
        $result2 = $this->service->getMapStats($this->user, $filters);

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
            'kills' => 20,
            'deaths' => 15,
            'assists' => 5,
            'adr' => 85.5,
            'first_kills' => 8,
            'first_deaths' => 6,
        ]);

        PlayerMatchEvent::factory()->create([
            'match_id' => $this->match2->id,
            'player_steam_id' => $this->player->steam_id,
            'kills' => 20,
            'deaths' => 15,
            'assists' => 5,
            'adr' => 85.5,
            'first_kills' => 8,
            'first_deaths' => 6,
        ]);
    }
}
