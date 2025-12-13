<?php

namespace Tests\Unit\Services;

use App\Enums\MatchType;
use App\Models\DemoProcessingJob;
use App\Models\GameMatch;
use App\Services\Demo\MatchTimeExtractor;
use App\Services\Integrations\FaceIT\FaceITRepository;
use App\Services\MatchTimeExtraction\FaceItMatchStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class MatchTimeExtractorTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_extract_returns_null_when_no_strategy_available(): void
    {
        $job = DemoProcessingJob::factory()->create();
        $match = GameMatch::factory()->create([
            'match_type' => MatchType::OTHER,
        ]);
        $job->update(['match_id' => $match->id]);

        $extractor = new MatchTimeExtractor($job);

        $result = $extractor->extract();

        $this->assertNull($result);
    }

    public function test_extract_returns_null_when_no_match(): void
    {
        $job = DemoProcessingJob::factory()->create();

        $extractor = new MatchTimeExtractor($job);

        $result = $extractor->extract();

        $this->assertNull($result);
    }

    public function test_extract_returns_null_when_no_original_file_name(): void
    {
        $job = DemoProcessingJob::factory()->create([
            'original_file_name' => null,
        ]);
        $match = GameMatch::factory()->create([
            'match_type' => MatchType::FACEIT,
        ]);
        $job->update(['match_id' => $match->id]);

        $extractor = new MatchTimeExtractor($job);

        $result = $extractor->extract();

        $this->assertNull($result);
    }

    public function test_extract_uses_faceit_strategy_for_faceit_match(): void
    {
        $job = DemoProcessingJob::factory()->create([
            'original_file_name' => '1-25e72cdb-ac23-4237-a95d-701603b58681-1-1.dem',
        ]);
        $match = GameMatch::factory()->create([
            'match_type' => MatchType::FACEIT,
            'map' => 'de_dust2',
            'winning_team_score' => 16,
            'losing_team_score' => 14,
        ]);
        $job->update(['match_id' => $match->id]);

        $faceitRepository = Mockery::mock(FaceITRepository::class);
        $faceitRepository
            ->shouldReceive('getMatchDetails')
            ->once()
            ->andReturn([
                'started_at' => 1760394042,
                'teams' => [
                    [
                        'roster' => [],
                    ],
                ],
            ]);
        $faceitRepository
            ->shouldReceive('getMatchStats')
            ->once()
            ->andReturn([
                'rounds' => [
                    [
                        'round_stats' => [
                            'Map' => 'de_dust2',
                            'Score' => '16 / 14',
                        ],
                    ],
                ],
            ]);

        // Bind the mock to the container so app() resolves it
        $this->app->instance(FaceITRepository::class, $faceitRepository);

        $extractor = new MatchTimeExtractor($job);

        $result = $extractor->extract();

        $this->assertNotNull($result);
        $this->assertEquals(1760394042, $result->timestamp);
    }

    public function test_determine_strategy_sets_faceit_strategy_for_faceit_match(): void
    {
        $job = DemoProcessingJob::factory()->create();
        $match = GameMatch::factory()->create([
            'match_type' => MatchType::FACEIT,
        ]);
        $job->update(['match_id' => $match->id]);

        $extractor = new MatchTimeExtractor($job);

        // Use reflection to check the strategy
        $reflection = new \ReflectionClass($extractor);
        $strategyProperty = $reflection->getProperty('strategy');
        $strategyProperty->setAccessible(true);
        $strategy = $strategyProperty->getValue($extractor);

        $this->assertInstanceOf(FaceItMatchStrategy::class, $strategy);
    }

    public function test_determine_strategy_returns_null_for_other_match_types(): void
    {
        $job = DemoProcessingJob::factory()->create();
        $match = GameMatch::factory()->create([
            'match_type' => MatchType::MATCHMAKING,
        ]);
        $job->update(['match_id' => $match->id]);

        $extractor = new MatchTimeExtractor($job);

        $result = $extractor->extract();

        $this->assertNull($result);
    }
}
