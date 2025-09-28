<?php

namespace App\Benchmarks\Services;

use App\Benchmarks\BenchmarkInterface;
use App\Benchmarks\Traits\RefreshDatabase;
use App\Enums\Team;
use App\Models\GameMatch;
use App\Models\GrenadeEvent;
use App\Models\Player;
use App\Services\Matches\GrenadeExplorerService;
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
class GrenadeExplorerServiceBench implements BenchmarkInterface
{
    private GrenadeExplorerService $service;

    private GameMatch $match;

    private const int MAX_PLAYERS_PER_MATCH = 20;

    private const int MAX_GRENADE_EVENTS_PER_MATCH = 200;

    use RefreshDatabase;

    public function setUp(): void
    {
        // Set CACHE_ENABLED to false for benchmarking
        config(['app.cache_enabled' => false]);

        $this->service = new GrenadeExplorerService;

        $uniqueId = uniqid();

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

        // Create grenade events with varied types and data
        $grenadeTypes = ['Flashbang', 'HE Grenade', 'Smoke Grenade', 'Molotov', 'Incendiary Grenade', 'Decoy Grenade'];

        for ($i = 0; $i < self::MAX_GRENADE_EVENTS_PER_MATCH; $i++) {
            $player = $players->random();
            $grenadeType = $grenadeTypes[array_rand($grenadeTypes)];

            GrenadeEvent::factory()->create([
                'match_id' => $this->match->id,
                'player_steam_id' => $player->steam_id,
                'player_side' => $index % 2 === 0 ? 'CT' : 'T',
                'grenade_type' => $grenadeType,
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
                'throw_type' => ['lineup', 'reaction', 'pre_aim', 'utility'][array_rand(['lineup', 'reaction', 'pre_aim', 'utility'])],
                'effectiveness_rating' => rand(1, 10),
                'round_number' => rand(1, 30),
                'round_time' => rand(0, 120),
                'tick_timestamp' => rand(1, 100000),
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
    public function benchGetExplorer(): void
    {
        $this->service->getExplorer([], $this->match->id);
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
    public function benchGetExplorerWithGrenadeType(): void
    {
        $this->service->getExplorer(['grenade_type' => 'Flashbang'], $this->match->id);
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
    public function benchGetExplorerWithRound(): void
    {
        $this->service->getExplorer(['round_number' => 15], $this->match->id);
    }

    /**
     * @Revs(25)
     *
     * @Iterations(5)
     *
     * @Warmup(2)
     *
     * @Assert("mode(variant.time.avg) < 125ms")
     */
    public function benchGetExplorerWithComplexFilters(): void
    {
        $this->service->getExplorer([
            'grenade_type' => 'Flashbang',
            'round_number' => 15,
            'player_side' => 'CT',
            'throw_type' => 'utility',
        ], $this->match->id);
    }
}
