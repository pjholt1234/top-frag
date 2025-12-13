<?php

namespace Tests\Feature\Services;

use App\Enums\AchievementType;
use App\Models\Achievement;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\PlayerMatchEvent;
use App\Models\User;
use App\Services\Matches\PlayerComplexionService;
use App\Services\Player\PlayerCardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PlayerCardServiceTest extends TestCase
{
    use RefreshDatabase;

    private PlayerCardService $service;

    private User $user;

    private Player $player;

    private GameMatch $match1;

    private GameMatch $match2;

    protected function setUp(): void
    {
        parent::setUp();

        $playerComplexionService = new PlayerComplexionService;
        $this->service = new PlayerCardService($playerComplexionService);

        $this->user = User::factory()->create([
            'steam_id' => '76561198012345678',
            'steam_persona_name' => 'TestPlayer',
            'steam_avatar_full' => 'https://example.com/avatar.jpg',
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
    public function it_returns_empty_player_card_when_player_not_found()
    {
        $result = $this->service->getPlayerCard('76561198000000000');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('player_card', $result);
        $this->assertArrayHasKey('achievements', $result);
        $this->assertEquals('Unknown', $result['player_card']['username']);
        $this->assertEquals(0, $result['player_card']['total_matches']);
    }

    /** @test */
    public function it_returns_empty_player_card_when_no_matches_exist()
    {
        $result = $this->service->getPlayerCard($this->player->steam_id);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('player_card', $result);
        $this->assertArrayHasKey('achievements', $result);
        $this->assertEquals(0, $result['player_card']['total_matches']);
    }

    /** @test */
    public function it_returns_player_card_with_correct_structure()
    {
        $this->createPlayerMatchEvents();

        $result = $this->service->getPlayerCard($this->player->steam_id);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('player_card', $result);
        $this->assertArrayHasKey('achievements', $result);

        // Check player card structure
        $playerCard = $result['player_card'];
        $this->assertArrayHasKey('username', $playerCard);
        $this->assertArrayHasKey('avatar', $playerCard);
        $this->assertArrayHasKey('average_impact', $playerCard);
        $this->assertArrayHasKey('average_round_swing', $playerCard);
        $this->assertArrayHasKey('average_kd', $playerCard);
        $this->assertArrayHasKey('average_adr', $playerCard);
        $this->assertArrayHasKey('average_kills', $playerCard);
        $this->assertArrayHasKey('average_deaths', $playerCard);
        $this->assertArrayHasKey('total_kills', $playerCard);
        $this->assertArrayHasKey('total_deaths', $playerCard);
        $this->assertArrayHasKey('total_matches', $playerCard);
        $this->assertArrayHasKey('win_percentage', $playerCard);
        $this->assertArrayHasKey('player_complexion', $playerCard);

        // Check player complexion
        $complexion = $playerCard['player_complexion'];
        $this->assertArrayHasKey('opener', $complexion);
        $this->assertArrayHasKey('closer', $complexion);
        $this->assertArrayHasKey('support', $complexion);
        $this->assertArrayHasKey('fragger', $complexion);

        // Check achievements structure
        $achievements = $result['achievements'];
        $this->assertArrayHasKey('fragger', $achievements);
        $this->assertArrayHasKey('support', $achievements);
        $this->assertArrayHasKey('opener', $achievements);
        $this->assertArrayHasKey('closer', $achievements);
        $this->assertArrayHasKey('top_aimer', $achievements);
        $this->assertArrayHasKey('impact_player', $achievements);
        $this->assertArrayHasKey('difference_maker', $achievements);
    }

    /** @test */
    public function it_calculates_player_stats_correctly()
    {
        $this->createPlayerMatchEvents();

        $result = $this->service->getPlayerCard($this->player->steam_id);

        $playerCard = $result['player_card'];
        $this->assertEquals(2, $playerCard['total_matches']);
        $this->assertEquals(40, $playerCard['total_kills']); // 20 + 20
        $this->assertEquals(30, $playerCard['total_deaths']); // 15 + 15
        $this->assertEquals(20.0, $playerCard['average_kills']); // 40 / 2
        $this->assertEquals(15.0, $playerCard['average_deaths']); // 30 / 2
        $this->assertEquals(1.33, round($playerCard['average_kd'], 2)); // 40 / 30
        $this->assertEquals(85.5, $playerCard['average_adr']); // (85.5 + 85.5) / 2
    }

    /** @test */
    public function it_calculates_win_percentage_correctly()
    {
        $this->createPlayerMatchEvents();

        $result = $this->service->getPlayerCard($this->player->steam_id);

        $playerCard = $result['player_card'];
        // Player won match1 (team A) and match2 (team B)
        $this->assertEquals(100.0, $playerCard['win_percentage']);
    }

    /** @test */
    public function it_includes_achievement_counts()
    {
        $this->createPlayerMatchEvents();

        // Create achievements
        Achievement::factory()->create([
            'match_id' => $this->match1->id,
            'player_id' => $this->player->id,
            'award_name' => AchievementType::FRAGGER->value,
        ]);

        Achievement::factory()->create([
            'match_id' => $this->match1->id,
            'player_id' => $this->player->id,
            'award_name' => AchievementType::TOP_AIMER->value,
        ]);

        Achievement::factory()->create([
            'match_id' => $this->match2->id,
            'player_id' => $this->player->id,
            'award_name' => AchievementType::FRAGGER->value,
        ]);

        $result = $this->service->getPlayerCard($this->player->steam_id);

        $achievements = $result['achievements'];
        $this->assertEquals(2, $achievements['fragger']);
        $this->assertEquals(1, $achievements['top_aimer']);
        $this->assertEquals(0, $achievements['support']);
        $this->assertEquals(0, $achievements['opener']);
    }

    /** @test */
    public function it_uses_user_avatar_when_available()
    {
        $this->createPlayerMatchEvents();

        $result = $this->service->getPlayerCard($this->player->steam_id);

        $playerCard = $result['player_card'];
        $this->assertEquals('TestPlayer', $playerCard['username']);
        $this->assertEquals('https://example.com/avatar.jpg', $playerCard['avatar']);
    }

    /** @test */
    public function it_falls_back_to_player_name_when_no_user_exists()
    {
        // Create player without user
        $playerWithoutUser = Player::factory()->create([
            'steam_id' => '76561198099999999',
            'name' => 'PlayerWithoutUser',
        ]);

        $match = GameMatch::factory()->create();
        $match->players()->attach($playerWithoutUser->id, ['team' => 'A']);

        PlayerMatchEvent::factory()->create([
            'match_id' => $match->id,
            'player_steam_id' => $playerWithoutUser->steam_id,
        ]);

        $result = $this->service->getPlayerCard($playerWithoutUser->steam_id);

        $playerCard = $result['player_card'];
        $this->assertEquals('PlayerWithoutUser', $playerCard['username']);
    }

    /** @test */
    public function it_caches_player_card_results()
    {
        Cache::flush();
        $this->createPlayerMatchEvents();

        // First call should query database
        $result1 = $this->service->getPlayerCard($this->player->steam_id);

        // Second call should use cache
        $result2 = $this->service->getPlayerCard($this->player->steam_id);

        $this->assertEquals($result1, $result2);
    }

    /** @test */
    public function it_invalidates_cache_correctly()
    {
        Cache::flush();
        $this->createPlayerMatchEvents();

        $cacheKey = "player-card:{$this->player->steam_id}";

        // First call should cache
        $this->service->getPlayerCard($this->player->steam_id);
        $this->assertTrue(Cache::has($cacheKey));

        // Invalidate cache
        $this->service->invalidatePlayerCache($this->player->steam_id);
        $this->assertFalse(Cache::has($cacheKey));
    }

    /** @test */
    public function it_limits_to_last_20_matches()
    {
        // Create 25 matches
        for ($i = 0; $i < 25; $i++) {
            $match = GameMatch::factory()->create(['map' => 'de_dust2']);
            $match->players()->attach($this->player->id, ['team' => 'A']);

            PlayerMatchEvent::factory()->create([
                'match_id' => $match->id,
                'player_steam_id' => $this->player->steam_id,
                'kills' => 10,
            ]);
        }

        $result = $this->service->getPlayerCard($this->player->steam_id);

        // Should only include last 20 matches
        $this->assertEquals(20, $result['player_card']['total_matches']);
        $this->assertEquals(200, $result['player_card']['total_kills']); // 20 matches * 10 kills
    }

    /** @test */
    public function it_handles_zero_division_gracefully()
    {
        PlayerMatchEvent::factory()->create([
            'match_id' => $this->match1->id,
            'player_steam_id' => $this->player->steam_id,
            'kills' => 20,
            'deaths' => 0, // Zero deaths
            'adr' => 100,
        ]);

        $result = $this->service->getPlayerCard($this->player->steam_id);

        // Should not throw any errors
        $this->assertIsArray($result);
        $this->assertArrayHasKey('player_card', $result);
        // When deaths is 0, K/D should be 0 (not infinity)
        $this->assertEquals(0, $result['player_card']['average_kd']);
    }

    /** @test */
    public function it_calculates_average_impact_and_round_swing()
    {
        PlayerMatchEvent::factory()->create([
            'match_id' => $this->match1->id,
            'player_steam_id' => $this->player->steam_id,
            'average_impact' => 1.2,
            'match_swing_percent' => 15.5,
        ]);

        PlayerMatchEvent::factory()->create([
            'match_id' => $this->match2->id,
            'player_steam_id' => $this->player->steam_id,
            'average_impact' => 1.5,
            'match_swing_percent' => 18.0,
        ]);

        $result = $this->service->getPlayerCard($this->player->steam_id);

        $playerCard = $result['player_card'];
        $this->assertEquals(1.35, $playerCard['average_impact']); // (1.2 + 1.5) / 2
        $this->assertEquals(16.75, $playerCard['average_round_swing']); // (15.5 + 18.0) / 2
    }

    /** @test */
    public function it_returns_all_achievement_types_as_zero_when_none_exist()
    {
        $this->createPlayerMatchEvents();

        $result = $this->service->getPlayerCard($this->player->steam_id);

        $achievements = $result['achievements'];
        $this->assertEquals(0, $achievements['fragger']);
        $this->assertEquals(0, $achievements['support']);
        $this->assertEquals(0, $achievements['opener']);
        $this->assertEquals(0, $achievements['closer']);
        $this->assertEquals(0, $achievements['top_aimer']);
        $this->assertEquals(0, $achievements['impact_player']);
        $this->assertEquals(0, $achievements['difference_maker']);
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
            'adr' => 85.5,
            'average_impact' => 1.2,
            'match_swing_percent' => 15.5,
        ]);

        PlayerMatchEvent::factory()->create([
            'match_id' => $this->match2->id,
            'player_steam_id' => $this->player->steam_id,
            'kills' => 20,
            'deaths' => 15,
            'adr' => 85.5,
            'average_impact' => 1.2,
            'match_swing_percent' => 15.5,
        ]);
    }
}
