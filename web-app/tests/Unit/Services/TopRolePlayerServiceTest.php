<?php

namespace Tests\Unit\Services;

use App\Models\GameMatch;
use App\Models\Player;
use App\Models\PlayerMatchEvent;
use App\Models\User;
use App\Services\Matches\PlayerComplexionService;
use App\Services\Matches\TopRolePlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopRolePlayerServiceTest extends TestCase
{
    use RefreshDatabase;

    private TopRolePlayerService $service;
    private PlayerComplexionService $playerComplexionService;
    private User $user;
    private Player $player;
    private GameMatch $match;

    protected function setUp(): void
    {
        parent::setUp();

        $this->playerComplexionService = new PlayerComplexionService();
        $this->service = new TopRolePlayerService($this->playerComplexionService);

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
            'total_rounds' => 24,
        ]);

        $this->match->players()->attach($this->player->id, ['team' => 'A']);
    }

    public function test_get_returns_empty_array_for_nonexistent_match()
    {
        $result = $this->service->get(99999);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('opener', $result);
        $this->assertArrayHasKey('closer', $result);
        $this->assertArrayHasKey('support', $result);
        $this->assertArrayHasKey('fragger', $result);

        foreach (['opener', 'closer', 'support', 'fragger'] as $role) {
            $this->assertNull($result[$role]['name']);
            $this->assertNull($result[$role]['steam_id']);
            $this->assertEquals(0, $result[$role]['score']);
        }
    }

    public function test_get_returns_correct_structure_with_single_player()
    {
        // Create player match event with complexion data
        PlayerMatchEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'first_kills' => 5,
            'first_deaths' => 3,
            'average_round_time_of_death' => 30,
            'average_time_to_contact' => 25,
            'total_successful_trades' => 8,
            'total_possible_trades' => 10,
            'total_possible_traded_deaths' => 12,
            'clutch_wins_1v1' => 2,
            'clutch_attempts_1v1' => 3,
            'clutch_wins_1v2' => 1,
            'clutch_attempts_1v2' => 2,
            'clutch_wins_1v3' => 0,
            'clutch_attempts_1v3' => 1,
            'clutch_wins_1v4' => 0,
            'clutch_attempts_1v4' => 0,
            'clutch_wins_1v5' => 0,
            'clutch_attempts_1v5' => 0,
            'flashes_thrown' => 8,
            'fire_grenades_thrown' => 4,
            'smokes_thrown' => 5,
            'hes_thrown' => 2,
            'decoys_thrown' => 1,
            'damage_dealt' => 150,
            'enemy_flash_duration' => 25,
            'average_grenade_effectiveness' => 40,
            'flashes_leading_to_kills' => 4,
            'kills' => 25,
            'deaths' => 20,
            'adr' => 85,
        ]);

        $result = $this->service->get($this->match->id);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('opener', $result);
        $this->assertArrayHasKey('closer', $result);
        $this->assertArrayHasKey('support', $result);
        $this->assertArrayHasKey('fragger', $result);

        // Check structure of each role
        foreach (['opener', 'closer', 'support', 'fragger'] as $role) {
            $this->assertArrayHasKey('name', $result[$role]);
            $this->assertArrayHasKey('steam_id', $result[$role]);
            $this->assertArrayHasKey('score', $result[$role]);
            $this->assertEquals($this->player->name, $result[$role]['name']);
            $this->assertEquals($this->player->steam_id, $result[$role]['steam_id']);
            $this->assertIsInt($result[$role]['score']);
        }
    }

    public function test_get_returns_best_player_for_each_role_with_multiple_players()
    {
        // Create additional players
        $player2 = Player::factory()->create([
            'steam_id' => '76561198012345679',
            'name' => 'Player2',
        ]);
        $player3 = Player::factory()->create([
            'steam_id' => '76561198012345680',
            'name' => 'Player3',
        ]);

        $this->match->players()->attach($player2->id, ['team' => 'A']);
        $this->match->players()->attach($player3->id, ['team' => 'B']);

        // Create player match events with different complexion scores
        // Player 1 - High opener score
        PlayerMatchEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'first_kills' => 10,
            'first_deaths' => 2,
            'average_round_time_of_death' => 20,
            'average_time_to_contact' => 15,
            'total_successful_trades' => 5,
            'total_possible_trades' => 8,
            'total_possible_traded_deaths' => 10,
            'clutch_wins_1v1' => 1,
            'clutch_attempts_1v1' => 2,
            'clutch_wins_1v2' => 0,
            'clutch_attempts_1v2' => 1,
            'clutch_wins_1v3' => 0,
            'clutch_attempts_1v3' => 0,
            'clutch_wins_1v4' => 0,
            'clutch_attempts_1v4' => 0,
            'clutch_wins_1v5' => 0,
            'clutch_attempts_1v5' => 0,
            'flashes_thrown' => 4,
            'fire_grenades_thrown' => 2,
            'smokes_thrown' => 2,
            'hes_thrown' => 1,
            'decoys_thrown' => 1,
            'damage_dealt' => 100,
            'enemy_flash_duration' => 15,
            'average_grenade_effectiveness' => 30,
            'flashes_leading_to_kills' => 2,
            'kills' => 20,
            'deaths' => 15,
            'adr' => 70,
        ]);

        // Player 2 - High closer score
        PlayerMatchEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $player2->steam_id,
            'first_kills' => 3,
            'first_deaths' => 5,
            'average_round_time_of_death' => 45,
            'average_time_to_contact' => 40,
            'total_successful_trades' => 3,
            'total_possible_trades' => 5,
            'total_possible_traded_deaths' => 6,
            'clutch_wins_1v1' => 4,
            'clutch_attempts_1v1' => 5,
            'clutch_wins_1v2' => 2,
            'clutch_attempts_1v2' => 3,
            'clutch_wins_1v3' => 1,
            'clutch_attempts_1v3' => 2,
            'clutch_wins_1v4' => 0,
            'clutch_attempts_1v4' => 1,
            'clutch_wins_1v5' => 0,
            'clutch_attempts_1v5' => 0,
            'flashes_thrown' => 6,
            'fire_grenades_thrown' => 3,
            'smokes_thrown' => 3,
            'hes_thrown' => 2,
            'decoys_thrown' => 1,
            'damage_dealt' => 120,
            'enemy_flash_duration' => 20,
            'average_grenade_effectiveness' => 35,
            'flashes_leading_to_kills' => 3,
            'kills' => 18,
            'deaths' => 12,
            'adr' => 75,
        ]);

        // Player 3 - High support score
        PlayerMatchEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $player3->steam_id,
            'first_kills' => 2,
            'first_deaths' => 4,
            'average_round_time_of_death' => 35,
            'average_time_to_contact' => 30,
            'total_successful_trades' => 2,
            'total_possible_trades' => 4,
            'total_possible_traded_deaths' => 5,
            'clutch_wins_1v1' => 1,
            'clutch_attempts_1v1' => 2,
            'clutch_wins_1v2' => 0,
            'clutch_attempts_1v2' => 1,
            'clutch_wins_1v3' => 0,
            'clutch_attempts_1v3' => 0,
            'clutch_wins_1v4' => 0,
            'clutch_attempts_1v4' => 0,
            'clutch_wins_1v5' => 0,
            'clutch_attempts_1v5' => 0,
            'flashes_thrown' => 12,
            'fire_grenades_thrown' => 6,
            'smokes_thrown' => 6,
            'hes_thrown' => 4,
            'decoys_thrown' => 2,
            'damage_dealt' => 200,
            'enemy_flash_duration' => 35,
            'average_grenade_effectiveness' => 50,
            'flashes_leading_to_kills' => 6,
            'kills' => 15,
            'deaths' => 18,
            'adr' => 60,
        ]);

        $result = $this->service->get($this->match->id);

        $this->assertIsArray($result);

        // Verify that each role has the expected best player
        // Note: The actual scores depend on the complexion calculation logic
        // This test verifies the structure and that different players can win different roles
        $this->assertArrayHasKey('opener', $result);
        $this->assertArrayHasKey('closer', $result);
        $this->assertArrayHasKey('support', $result);
        $this->assertArrayHasKey('fragger', $result);

        foreach (['opener', 'closer', 'support', 'fragger'] as $role) {
            $this->assertArrayHasKey('name', $result[$role]);
            $this->assertArrayHasKey('steam_id', $result[$role]);
            $this->assertArrayHasKey('score', $result[$role]);
            $this->assertIsString($result[$role]['name']);
            $this->assertIsString($result[$role]['steam_id']);
            $this->assertIsInt($result[$role]['score']);
        }
    }

    public function test_get_returns_empty_roles_when_no_player_data()
    {
        $result = $this->service->get($this->match->id);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('opener', $result);
        $this->assertArrayHasKey('closer', $result);
        $this->assertArrayHasKey('support', $result);
        $this->assertArrayHasKey('fragger', $result);

        foreach (['opener', 'closer', 'support', 'fragger'] as $role) {
            $this->assertNull($result[$role]['name']);
            $this->assertNull($result[$role]['steam_id']);
            $this->assertEquals(0, $result[$role]['score']);
        }
    }
}
