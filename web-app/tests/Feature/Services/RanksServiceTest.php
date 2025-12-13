<?php

namespace Tests\Feature\Services;

use App\Models\Player;
use App\Models\PlayerRank;
use App\Models\User;
use App\Services\Player\RanksService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RanksServiceTest extends TestCase
{
    use RefreshDatabase;

    private RanksService $service;

    private User $user;

    private Player $player;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new RanksService;

        $this->user = User::factory()->create([
            'steam_id' => '76561198012345678',
            'steam_persona_name' => 'TestPlayer',
        ]);

        $this->player = Player::factory()->create([
            'steam_id' => '76561198012345678',
            'name' => 'TestPlayer',
        ]);
    }

    /** @test */
    public function it_returns_rank_stats_with_correct_structure()
    {
        PlayerRank::factory()->count(3)->create([
            'player_id' => $this->player->id,
            'rank_type' => 'competitive',
            'map' => 'de_dust2',
            'rank' => 'Global Elite',
            'rank_value' => 18,
        ]);

        PlayerRank::factory()->count(2)->create([
            'player_id' => $this->player->id,
            'rank_type' => 'premier',
            'map' => null,
            'rank' => '20000',
            'rank_value' => 20000,
        ]);

        $filters = ['past_match_count' => 10];
        $result = $this->service->getRankStats($this->user, $filters);

        $this->assertArrayHasKey('competitive', $result);
        $this->assertArrayHasKey('premier', $result);
        $this->assertArrayHasKey('faceit', $result);

        // Check competitive structure (has maps)
        $competitive = $result['competitive'];
        $this->assertArrayHasKey('rank_type', $competitive);
        $this->assertArrayHasKey('maps', $competitive);
        $this->assertIsArray($competitive['maps']);

        if (! empty($competitive['maps'])) {
            $mapRank = $competitive['maps'][0];
            $this->assertArrayHasKey('map', $mapRank);
            $this->assertArrayHasKey('current_rank', $mapRank);
            $this->assertArrayHasKey('current_rank_value', $mapRank);
            $this->assertArrayHasKey('trend', $mapRank);
            $this->assertArrayHasKey('history', $mapRank);
        }

        // Check premier structure (no maps)
        $premier = $result['premier'];
        $this->assertArrayHasKey('rank_type', $premier);
        $this->assertArrayHasKey('current_rank', $premier);
        $this->assertArrayHasKey('current_rank_value', $premier);
        $this->assertArrayHasKey('trend', $premier);
        $this->assertArrayHasKey('history', $premier);
    }

    /** @test */
    public function it_returns_empty_rank_stats_when_user_has_no_steam_id()
    {
        $user = User::factory()->create(['steam_id' => null]);
        $filters = [];

        $result = $this->service->getRankStats($user, $filters);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('competitive', $result);
        $this->assertArrayHasKey('premier', $result);
        $this->assertArrayHasKey('faceit', $result);
        $this->assertEquals([], $result['competitive']);
        $this->assertEquals([], $result['premier']);
        $this->assertEquals([], $result['faceit']);
    }

    /** @test */
    public function it_caches_rank_stats_results()
    {
        Cache::flush();
        PlayerRank::factory()->count(2)->create([
            'player_id' => $this->player->id,
            'rank_type' => 'premier',
            'rank' => '20000',
            'rank_value' => 20000,
        ]);

        $filters = ['past_match_count' => 10];

        // First call should query database
        $result1 = $this->service->getRankStats($this->user, $filters);

        // Second call should use cache
        $result2 = $this->service->getRankStats($this->user, $filters);

        $this->assertEquals($result1, $result2);
    }
}
