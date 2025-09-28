<?php

namespace App\Benchmarks\Services;

use App\Benchmarks\BenchmarkInterface;
use App\Benchmarks\Traits\RefreshDatabase;
use App\Enums\Team;
use App\Models\DamageEvent;
use App\Models\GameMatch;
use App\Models\GrenadeEvent;
use App\Models\GunfightEvent;
use App\Models\Player;
use App\Models\User;
use App\Services\MatchHistoryService;
use PhpBench\Benchmark\Metadata\Annotations\AfterMethods;
use PhpBench\Benchmark\Metadata\Annotations\Assert;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\Revs;
use PhpBench\Benchmark\Metadata\Annotations\Warmup;

/**
 * @BeforeMethods({"setUp"})
 *
 * @AfterMethods({"tearDown"})
 */
class UserMatchHistoryServiceBench implements BenchmarkInterface
{
    private MatchHistoryService $service;

    private User $user;

    private Player $player;

    private GameMatch $match;

    private User $userWithMultipleMatches;

    private User $userWithoutPlayer;

    private const int MAX_PLAYERS_PER_MATCH = 20;

    private const int MAX_DAMAGE_EVENTS_PER_MATCH = 200;

    private const int MAX_GUNFIGHT_EVENTS_PER_MATCH = 50;

    private const int MAX_GRENADE_EVENTS_PER_MATCH = 100;

    use RefreshDatabase;

    public function setUp(): void
    {
        // Set CACHE_ENABLED to false for benchmarking
        config(['app.cache_enabled' => false]);

        $this->service = new MatchHistoryService(
            matchDetailsService: new \App\Services\Matches\MatchDetailsService
        );

        $uniqueId = uniqid();

        $this->user = User::factory()->create([
            'name' => 'Benchmark User ' . $uniqueId,
            'email' => 'benchmark' . $uniqueId . '@test.com',
            'steam_id' => 'STEAM_0:1:' . rand(100000000, 999999999),
        ]);

        $this->player = Player::factory()->create([
            'steam_id' => $this->user->steam_id,
            'name' => 'Benchmark Player ' . $uniqueId,
        ]);

        $this->match = GameMatch::factory()->create([
            'map' => 'de_dust2',
            'winning_team' => 'A',
            'winning_team_score' => 16,
            'losing_team_score' => 14,
            'match_type' => 'mm',
            'total_rounds' => 30,
            'playback_ticks' => 100000,
        ]);

        $players = Player::factory()->count(self::MAX_PLAYERS_PER_MATCH)->create();

        foreach ($players as $index => $player) {
            $this->match->players()->attach($player->id, [
                'team' => $index % 2 === 0 ? Team::TEAM_A : Team::TEAM_B,
            ]);
        }

        $this->match->players()->attach($this->player->id, [
            'team' => Team::TEAM_A,
        ]);

        // Create damage events
        for ($i = 0; $i < self::MAX_DAMAGE_EVENTS_PER_MATCH; $i++) {
            DamageEvent::factory()->create([
                'match_id' => $this->match->id,
                'attacker_steam_id' => $players->random()->steam_id,
                'victim_steam_id' => $players->random()->steam_id,
                'health_damage' => rand(10, 100),
                'armor_damage' => rand(0, 50),
                'damage' => rand(10, 100),
                'headshot' => rand(0, 1),
                'weapon' => 'ak47',
                'round_number' => rand(1, 30),
                'round_time' => rand(0, 120),
                'tick_timestamp' => rand(1, 100000),
            ]);
        }

        for ($i = 0; $i < self::MAX_GUNFIGHT_EVENTS_PER_MATCH; $i++) {
            $player1 = $players->random();
            $player2 = $players->random();

            GunfightEvent::factory()->create([
                'match_id' => $this->match->id,
                'player_1_steam_id' => $player1->steam_id,
                'player_2_steam_id' => $player2->steam_id,
                'victor_steam_id' => $players->random()->steam_id,
                'player_1_hp_start' => rand(1, 100),
                'player_2_hp_start' => rand(1, 100),
                'player_1_armor' => rand(0, 100),
                'player_2_armor' => rand(0, 100),
                'player_1_flashed' => rand(0, 1),
                'player_2_flashed' => rand(0, 1),
                'player_1_weapon' => 'ak47',
                'player_2_weapon' => 'm4a1',
                'player_1_equipment_value' => rand(1000, 5000),
                'player_2_equipment_value' => rand(1000, 5000),
                'player_1_x' => rand(-1000, 1000),
                'player_1_y' => rand(-1000, 1000),
                'player_1_z' => rand(-100, 100),
                'player_2_x' => rand(-1000, 1000),
                'player_2_y' => rand(-1000, 1000),
                'player_2_z' => rand(-100, 100),
                'distance' => rand(10, 500),
                'headshot' => rand(0, 1),
                'wallbang' => rand(0, 1),
                'penetrated_objects' => rand(0, 3),
                'damage_dealt' => rand(10, 100),
                'round_number' => rand(1, 30),
                'round_time' => rand(0, 120),
                'tick_timestamp' => rand(1, 100000),
            ]);
        }

        for ($i = 0; $i < self::MAX_GRENADE_EVENTS_PER_MATCH; $i++) {
            GrenadeEvent::factory()->create([
                'match_id' => $this->match->id,
                'player_steam_id' => $players->random()->steam_id,
                'grenade_type' => 'Flashbang',
                'player_x' => rand(-1000, 1000),
                'player_y' => rand(-1000, 1000),
                'player_z' => rand(-100, 100),
                'player_aim_x' => rand(-1, 1),
                'player_aim_y' => rand(-1, 1),
                'player_aim_z' => rand(-1, 1),
                'grenade_final_x' => rand(-1000, 1000),
                'grenade_final_y' => rand(-1000, 1000),
                'grenade_final_z' => rand(-100, 100),
                'damage_dealt' => rand(0, 50),
                'friendly_flash_duration' => rand(0, 5),
                'enemy_flash_duration' => rand(0, 10),
                'friendly_players_affected' => rand(0, 5),
                'enemy_players_affected' => rand(0, 5),
                'throw_type' => 'utility',
                'effectiveness_rating' => rand(1, 10),
                'round_number' => rand(1, 30),
                'round_time' => rand(0, 120),
                'tick_timestamp' => rand(1, 100000),
            ]);
        }

        // Create user with multiple matches for multiple matches benchmark
        $this->userWithMultipleMatches = User::factory()->create([
            'name' => 'Multiple Matches User ' . $uniqueId,
            'email' => 'multiple' . $uniqueId . '@test.com',
            'steam_id' => 'STEAM_0:1:' . rand(100000000, 999999999),
        ]);

        $playerWithMultipleMatches = Player::factory()->create([
            'steam_id' => $this->userWithMultipleMatches->steam_id,
            'name' => 'Multiple Matches Player ' . $uniqueId,
        ]);

        // Create additional matches for the user
        for ($i = 0; $i < 3; $i++) {
            $match = GameMatch::factory()->create([
                'map' => 'de_mirage',
                'winning_team' => 'B',
                'winning_team_score' => 13,
                'losing_team_score' => 11,
                'match_type' => 'mm',
                'total_rounds' => 24,
                'playback_ticks' => 80000,
            ]);

            $match->players()->attach($playerWithMultipleMatches->id, [
                'team' => Team::TEAM_B,
            ]);

            // Add minimal events to each match for benchmarking
            for ($j = 0; $j < 10; $j++) {
                DamageEvent::factory()->create([
                    'match_id' => $match->id,
                    'attacker_steam_id' => $playerWithMultipleMatches->steam_id,
                    'victim_steam_id' => 'STEAM_0:1:999999',
                    'health_damage' => rand(10, 100),
                    'armor_damage' => rand(0, 50),
                    'damage' => rand(10, 100),
                    'headshot' => rand(0, 1),
                    'weapon' => 'ak47',
                    'round_number' => rand(1, 24),
                    'round_time' => rand(0, 120),
                    'tick_timestamp' => rand(1, 80000),
                ]);
            }
        }

        // Create user without player for edge case benchmark
        $this->userWithoutPlayer = User::factory()->create([
            'name' => 'User Without Player ' . $uniqueId,
            'email' => 'noplayer' . $uniqueId . '@test.com',
            'steam_id' => 'STEAM_0:1:' . rand(100000000, 999999999),
        ]);
    }

    public function tearDown(): void
    {
        // Cleanup is handled by RefreshDatabase trait
    }

    /**
     * @Revs(25)
     *
     * @Iterations(5)
     *
     * @Warmup(2)
     *
     * @Assert("mode(variant.time.avg) < 100ms")
     */
    public function benchAggregateMatchData(): void
    {
        $this->service->getPaginatedMatchHistory($this->user, 10, 1);
    }

    /**
     * @Revs(50)
     *
     * @Iterations(5)
     *
     * @Warmup(2)
     *
     * @Assert("mode(variant.time.avg) < 75ms")
     */
    public function benchGetMatchDetails(): void
    {
        $this->service->getPaginatedMatchHistory($this->user, 1, 1);
    }

    /**
     * @Revs(100)
     *
     * @Iterations(5)
     *
     * @Warmup(2)
     *
     * @Assert("mode(variant.time.avg) < 50ms")
     */
    public function benchGetPlayerStats(): void
    {
        // Test player stats calculation through match details
        $this->service->getPaginatedMatchHistory($this->user, 1, 1);
    }

    /**
     * @Revs(50)
     *
     * @Iterations(5)
     *
     * @Warmup(2)
     *
     * @Assert("mode(variant.time.avg) < 100ms")
     */
    public function benchGetMatchHistoryWithFilters(): void
    {
        // Test match history with filters
        $this->service->getPaginatedMatchHistory($this->user, 10, 1, ['match_type' => 'mm']);
    }

    /**
     * @Revs(10)
     *
     * @Iterations(3)
     *
     * @Warmup(1)
     *
     * @Assert("mode(variant.time.avg) < 150ms")
     */
    public function benchMultipleMatchesAggregation(): void
    {
        $this->service->getPaginatedMatchHistory($this->userWithMultipleMatches, 10, 1);
    }

    /**
     * @Revs(25)
     *
     * @Iterations(3)
     *
     * @Warmup(1)
     *
     * @Assert("mode(variant.time.avg) < 75ms")
     */
    public function benchUserWithoutPlayer(): void
    {
        $this->service->getPaginatedMatchHistory($this->userWithoutPlayer, 10, 1);
    }
}
