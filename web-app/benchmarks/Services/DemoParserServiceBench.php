<?php

namespace App\Benchmarks\Services;

use App\Benchmarks\BenchmarkInterface;
use App\Benchmarks\Traits\RefreshDatabase;
use App\Enums\MatchEventType;
use App\Enums\ProcessingStatus;
use App\Models\DemoProcessingJob;
use App\Models\GameMatch;
use App\Services\DemoParserService;
use Database\Factories\DataFactories\DamageEventDataFactory;
use Database\Factories\DataFactories\GrenadeEventDataFactory;
use Database\Factories\DataFactories\GunfightEventDataFactory;
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
class DemoParserServiceBench implements BenchmarkInterface
{
    private DemoParserService $service;

    private DemoProcessingJob $job;

    private GameMatch $match;

    private array $damageEventData;

    private array $gunfightEventData;

    private array $grenadeEventData;

    private const MAX_DAMAGE_EVENTS = 200;

    private const MAX_GUNFIGHT_EVENTS = 100;

    private const MAX_GRENADE_EVENTS = 100;

    private const MAX_ROUND_EVENTS = 50;

    use RefreshDatabase;

    public function setUp(): void
    {
        $this->service = new DemoParserService;

        $this->job = DemoProcessingJob::create([
            'uuid' => 'benchmark-test-'.uniqid(),
            'processing_status' => ProcessingStatus::PROCESSING,
            'progress_percentage' => 0,
        ]);

        $this->match = GameMatch::create([
            'match_hash' => null,
            'map' => 'de_dust2',
            'winning_team' => 'A',
            'winning_team_score' => 13,
            'losing_team_score' => 14,
            'match_type' => 'mm',
            'total_rounds' => 30,
            'playback_ticks' => 100000,
        ]);

        $this->job->update(['match_id' => $this->match->id]);

        $this->damageEventData = DamageEventDataFactory::create(self::MAX_DAMAGE_EVENTS);
        $this->gunfightEventData = GunfightEventDataFactory::create(self::MAX_GUNFIGHT_EVENTS);
        $this->grenadeEventData = GrenadeEventDataFactory::create(self::MAX_GRENADE_EVENTS);
    }

    public function tearDown(): void
    {
        $this->match->delete();
        $this->job->delete();
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
    public function benchCreateDamageEvent(): void
    {
        $this->service->createMatchEvent(
            $this->job->uuid,
            $this->damageEventData,
            MatchEventType::DAMAGE->value
        );
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
    public function benchCreateGunfightEvent(): void
    {
        $this->service->createMatchEvent(
            $this->job->uuid,
            $this->gunfightEventData,
            MatchEventType::GUNFIGHT->value
        );
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
    public function benchCreateGrenadeEvent(): void
    {
        $this->service->createMatchEvent(
            $this->job->uuid,
            $this->grenadeEventData,
            MatchEventType::GRENADE->value
        );
    }

    /**
     * @Revs(25)
     *
     * @Iterations(3)
     *
     * @Warmup(1)
     *
     * @Assert("mode(variant.time.avg) < 100ms")
     */
    public function benchCreateAllEventTypes(): void
    {
        $this->service->createMatchEvent(
            $this->job->uuid,
            $this->damageEventData,
            MatchEventType::DAMAGE->value
        );

        $this->service->createMatchEvent(
            $this->job->uuid,
            $this->gunfightEventData,
            MatchEventType::GUNFIGHT->value
        );

        $this->service->createMatchEvent(
            $this->job->uuid,
            $this->grenadeEventData,
            MatchEventType::GRENADE->value
        );
    }
}
