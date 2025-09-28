<?php

namespace App\Benchmarks\Services;

use App\Benchmarks\BenchmarkInterface;
use App\Benchmarks\Traits\RefreshDatabase;
use App\Enums\Team;
use App\Models\GameMatch;
use App\Models\GrenadeEvent;
use App\Models\Player;
use App\Models\PlayerRoundEvent;
use App\Models\User;
use App\Services\Matches\UtilityAnalysisService;
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
class UtilityAnalysisServiceBench implements BenchmarkInterface
{
    private UtilityAnalysisService $service;

    private User $user;

    private Player $player;

    private GameMatch $match;

    private const int MAX_PLAYERS_PER_MATCH = 20;

    private const int MAX_GRENADE_EVENTS_PER_MATCH = 100;

    private const int MAX_PLAYER_ROUND_EVENTS_PER_MATCH = 200;

    use RefreshDatabase;

    public function setUp(): void
    {
        // Set CACHE_ENABLED to false for benchmarking
        config(['app.cache_enabled' => false]);

        $this->service = new UtilityAnalysisService;

        $uniqueId = uniqid();

        $this->user = User::factory()->create([
            'name' => 'Benchmark User '.$uniqueId,
            'email' => 'benchmark'.$uniqueId.'@test.com',
            'steam_id' => 'STEAM_0:1:'.rand(100000000, 999999999),
        ]);

        $this->player = Player::factory()->create([
            'steam_id' => $this->user->steam_id,
            'name' => 'Benchmark Player '.$uniqueId,
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

        // Create grenade events
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

        // Create player round events
        for ($i = 0; $i < self::MAX_PLAYER_ROUND_EVENTS_PER_MATCH; $i++) {
            PlayerRoundEvent::factory()->create([
                'match_id' => $this->match->id,
                'player_steam_id' => $players->random()->steam_id,
                'round_number' => rand(1, 30),
                'kills' => rand(0, 5),
                'assists' => rand(0, 3),
                'died' => rand(0, 1),
                'damage' => rand(0, 500),
                'headshots' => rand(0, 2),
                'first_kill' => rand(0, 1),
                'first_death' => rand(0, 1),
                'flashes_thrown' => rand(0, 5),
                'friendly_flash_duration' => rand(0, 5),
                'enemy_flash_duration' => rand(0, 10),
                'friendly_players_affected' => rand(0, 5),
                'enemy_players_affected' => rand(0, 5),
                'flashes_leading_to_kill' => rand(0, 2),
                'flashes_leading_to_death' => rand(0, 1),
                'grenade_effectiveness' => rand(0, 100) / 100,
            ]);
        }
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
    public function benchGetAnalysis(): void
    {
        $this->service->getAnalysis($this->user, $this->match->id);
    }

    /**
     * @Revs(25)
     *
     * @Iterations(5)
     *
     * @Warmup(2)
     *
     * @Assert("mode(variant.time.avg) < 75ms")
     */
    public function benchGetAnalysisWithPlayer(): void
    {
        $this->service->getAnalysis($this->user, $this->match->id, $this->player->steam_id);
    }

    /**
     * @Revs(25)
     *
     * @Iterations(5)
     *
     * @Warmup(2)
     *
     * @Assert("mode(variant.time.avg) < 50ms")
     */
    public function benchGetAnalysisWithRound(): void
    {
        $this->service->getAnalysis($this->user, $this->match->id, null, 15);
    }

    /**
     * @Revs(25)
     *
     * @Iterations(5)
     *
     * @Warmup(2)
     *
     * @Assert("mode(variant.time.avg) < 75ms")
     */
    public function benchGetAnalysisWithPlayerAndRound(): void
    {
        $this->service->getAnalysis($this->user, $this->match->id, $this->player->steam_id, 15);
    }
}
