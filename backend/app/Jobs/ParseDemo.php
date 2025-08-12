<?php

namespace App\Jobs;

use App\Exceptions\ParserServiceConnectorException;
use App\Models\DemoProcessingJob;
use App\Models\GameMatch;
use App\Services\ParserServiceConnector;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ParseDemo implements ShouldQueue
{
    use Queueable;

    private readonly ParserServiceConnector $parserServiceConnector;

    public function __construct(private readonly string $filePath)
    {
        $this->parserServiceConnector = app(ParserServiceConnector::class);
    }

    public function handle(): void
    {
        try {
            $match = GameMatch::create();
            $job = DemoProcessingJob::create([
                'match_id' => $match->id,
            ]);
            $this->parserServiceConnector->checkServiceHealth();
            $response = $this->parserServiceConnector->uploadDemo($this->filePath, $job->uuid);

            Log::channel('parser')->info('Demo upload successful', [
                'job_id' => $job->uuid,
                'file_path' => $this->filePath,
                'response' => $response,
            ]);
        } catch (ParserServiceConnectorException $e) {
            return;
        } catch (\Exception $e) {
            Log::channel('parser')->error('Unexpected error in demo parsing job', [
                'file_path' => $this->filePath,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
