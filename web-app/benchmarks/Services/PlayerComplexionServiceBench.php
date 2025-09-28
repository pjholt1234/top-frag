<?php

namespace App\Benchmarks\Services;

use App\Benchmarks\BenchmarkInterface;
use App\Benchmarks\Traits\RefreshDatabase;
use App\Enums\Team;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\PlayerMatchEvent;
use App\Services\Matches\PlayerComplexionService;
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
class PlayerComplexionServiceBench implements BenchmarkInterface
{
    private PlayerComplexionService $service;

    private Player $player;

    private GameMatch $match;

    private const int MAX_PLAYERS_PER_MATCH = 20;

    use RefreshDatabase;

    public function setUp(): void
    {
        // Set CACHE_ENABLED to false for benchmarking
        config(['app.cache_enabled' => false]);

        $this->service = new PlayerComplexionService;

        $uniqueId = uniqid();

        $this->player = Player::factory()->create([
            'steam_id' => 'STEAM_0:1:'.rand(100000000, 999999999),
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

        // Create player match events with varied stats for complexion analysis
        foreach ($players as $player) {
            PlayerMatchEvent::factory()->create([
                'match_id' => $this->match->id,
                'player_steam_id' => $player->steam_id,
                'kills' => rand(10, 30),
                'deaths' => rand(10, 30),
                'assists' => rand(5, 15),
                'adr' => rand(50, 150),
                'first_kills' => rand(0, 5),
                'first_deaths' => rand(0, 5),
                'rank_value' => rand(1, 18),
            ]);
        }

        // Create player match event for our test player
        PlayerMatchEvent::factory()->create([
            'match_id' => $this->match->id,
            'player_steam_id' => $this->player->steam_id,
            'kills' => 25,
            'deaths' => 15,
            'assists' => 8,
            'adr' => 120,
            'first_kills' => 3,
            'first_deaths' => 2,
            'rank_value' => 12,
        ]);
    }

    public function tearDown(): void
    {
        // Cleanup is handled by RefreshDatabase trait
    }

    /**
     * @Revs(50)
     *
     * @Iterations(5)
     *
     * @Warmup(2)
     *
     * @Assert("mode(variant.time.avg) < 25ms")
     */
    public function benchGetComplexion(): void
    {
        $this->service->get($this->player->steam_id, $this->match->id);
    }

    /**
     * @Revs(100)
     *
     * @Iterations(5)
     *
     * @Warmup(2)
     *
     * @Assert("mode(variant.time.avg) < 15ms")
     */
    public function benchGetComplexionMultiplePlayers(): void
    {
        // Test with multiple players to simulate real usage
        $players = $this->match->players;
        foreach ($players->take(5) as $player) {
            $this->service->get($player->steam_id, $this->match->id);
        }
    }
}
