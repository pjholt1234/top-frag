<?php

namespace App\Benchmarks\Services;

use App\Benchmarks\BenchmarkInterface;
use App\Benchmarks\Traits\RefreshDatabase;
use App\Enums\Team;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\PlayerMatchEvent;
use App\Services\Matches\PlayerComplexionService;
use App\Services\Matches\TopRolePlayerService;
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
class TopRolePlayerServiceBench implements BenchmarkInterface
{
    private TopRolePlayerService $service;

    private GameMatch $match;

    private const int MAX_PLAYERS_PER_MATCH = 20;

    use RefreshDatabase;

    public function setUp(): void
    {
        // Set CACHE_ENABLED to false for benchmarking
        config(['app.cache_enabled' => false]);

        $this->service = new TopRolePlayerService(new PlayerComplexionService);

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

        // Create player match events with varied stats for role analysis
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
     * @Assert("mode(variant.time.avg) < 200ms")
     */
    public function benchGetTopRolePlayers(): void
    {
        $this->service->get($this->match->id);
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
    public function benchGetTopRolePlayersMultipleMatches(): void
    {
        // Test with multiple matches to simulate real usage
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

            $players = Player::factory()->count(10)->create();
            foreach ($players as $index => $player) {
                $match->players()->attach($player->id, [
                    'team' => $index % 2 === 0 ? Team::TEAM_A : Team::TEAM_B,
                ]);

                PlayerMatchEvent::factory()->create([
                    'match_id' => $match->id,
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

            $this->service->get($match->id);
        }
    }
}
