<?php

namespace App\Jobs;

use App\Exceptions\ParserServiceConnectorException;
use App\Models\DemoProcessingJob;
use App\Models\GameMatch;
use App\Models\User;
use App\Services\ParserServiceConnector;
use App\Services\RateLimiterService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ParseDemo implements ShouldQueue
{
    use Queueable;

    private readonly ParserServiceConnector $parserServiceConnector;

    public function __construct(
        private readonly string $filePath,
        private readonly ?User $user = null,
        private readonly ?int $matchId = null
    ) {
        $this->parserServiceConnector = app(ParserServiceConnector::class);
    }

    public function handle(): void
    {
        $rateLimiter = app(RateLimiterService::class);

        $rateLimiter->incrementParserServiceUsage();

        if (! $rateLimiter->checkParserServiceLimit()) {
            Log::warning('Parser service rate limit reached, requeuing job');
            $rateLimiter->decrementParserServiceUsage();
            $this->release(60);

            return;
        }

        try {
            if ($this->matchId) {
                $match = GameMatch::findOrFail($this->matchId);
            } else {
                $match = GameMatch::create([
                    'uploaded_by' => $this->user?->id,
                ]);
            }

            $job = DemoProcessingJob::where('match_id', $match->id)->first();

            if (empty($job)) {
                $job = DemoProcessingJob::create([
                    'match_id' => $match->id,
                    'user_id' => $this->user?->id,
                ]);
            }

            $this->parserServiceConnector->checkServiceHealth();
            $response = $this->parserServiceConnector->uploadDemo($this->filePath, $job->uuid);

            Log::channel('parser')->info('Demo upload successful', [
                'job_id' => $job->uuid,
                'file_path' => $this->filePath,
                'response' => $response,
            ]);
        } catch (ParserServiceConnectorException $e) {
            report($e);

            return;
        } catch (\Exception $e) {
            Log::channel('parser')->error('Unexpected error in demo parsing job', [
                'file_path' => $this->filePath,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        } finally {
            $rateLimiter->decrementParserServiceUsage();
        }
    }
}
