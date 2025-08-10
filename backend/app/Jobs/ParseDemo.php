<?php

namespace App\Jobs;

use App\Exceptions\ParserServiceConnectorException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Services\ParserServiceConnector;
use Illuminate\Support\Facades\Log;
use App\Models\DemoProcessingJob;

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
            $job = DemoProcessingJob::create();

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
