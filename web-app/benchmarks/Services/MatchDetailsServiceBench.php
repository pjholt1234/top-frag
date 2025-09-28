<?php

namespace App\Benchmarks\Services;

use App\Benchmarks\BenchmarkInterface;
use App\Benchmarks\Traits\RefreshDatabase;
use App\Enums\Team;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\PlayerMatchEvent;
use App\Models\User;
use App\Services\Matches\MatchDetailsService;
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
class MatchDetailsServiceBench implements BenchmarkInterface
{
    private MatchDetailsService $service;

    private User $user;

    private Player $player;

    private GameMatch $match;

    private const int MAX_PLAYERS_PER_MATCH = 20;

    use RefreshDatabase;

    public function setUp(): void
    {
        // Set CACHE_ENABLED to false for benchmarking
        config(['app.cache_enabled' => false]);

        $this->service = new MatchDetailsService;

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

        // Create player match events for scoreboard stats
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
     * @Revs(50)
     *
     * @Iterations(5)
     *
     * @Warmup(2)
     *
     * @Assert("mode(variant.time.avg) < 50ms")
     */
    public function benchGetDetails(): void
    {
        $this->service->getDetails($this->user, $this->match->id);
    }

    /**
     * @Revs(100)
     *
     * @Iterations(5)
     *
     * @Warmup(2)
     *
     * @Assert("mode(variant.time.avg) < 25ms")
     */
    public function benchGetDetailsMultipleMatches(): void
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

            $this->service->getDetails($this->user, $match->id);
        }
    }
}
