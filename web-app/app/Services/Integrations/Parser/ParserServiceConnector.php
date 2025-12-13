<?php

namespace App\Services\Integrations\Parser;

use App\Exceptions\ParserServiceConnectorException;
use Exception;
use Illuminate\Support\Facades\Http;

class ParserServiceConnector
{
    private ?string $base_url = null;

    private ?string $apiKey = null;

    private string $progressCallbackURL;

    private string $completionCallbackURL;

    private string $parseDemoURL;

    private const string PROGRESS_CALLBACK_ENDPOINT = '/api/job/callback/progress';

    private const string COMPLETION_CALLBACK_ENDPOINT = '/api/job/callback/completion';

    private const string PARSE_DEMO_ENDPOINT = '/api/parse-demo';

    public function __construct()
    {
        $this->base_url = config('services.parser.base_url');
        $this->apiKey = config('services.parser.api_key');

        $this->progressCallbackURL = config('app.url').self::PROGRESS_CALLBACK_ENDPOINT;
        $this->completionCallbackURL = config('app.url').self::COMPLETION_CALLBACK_ENDPOINT;
        $this->parseDemoURL = $this->base_url.self::PARSE_DEMO_ENDPOINT;
    }

    public function checkServiceHealth(): void
    {
        try {
            $response = Http::get($this->base_url.'/health');
        } catch (Exception) {
            throw ParserServiceConnectorException::serviceUnavailable();
        }

        if (! $response->successful()) {
            throw ParserServiceConnectorException::serviceUnavailable();
        }
    }

    public function uploadDemo(string $filePath, string $jobId): array|false
    {
        $this->checkServiceHealth();

        try {
            $request = Http::timeout(300);

            $request->withHeaders([
                'X-API-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ]);

            $formData = [
                'job_id' => $jobId,
                'progress_callback_url' => $this->progressCallbackURL,
                'completion_callback_url' => $this->completionCallbackURL,
            ];

            $response = $request
                ->attach(
                    'demo_file',
                    file_get_contents($filePath),
                    basename($filePath)
                )
                ->post(
                    $this->parseDemoURL,
                    $formData
                );
        } catch (Exception $e) {
            throw ParserServiceConnectorException::uploadFailed();
        }

        if (! $response->successful()) {
            throw ParserServiceConnectorException::uploadFailed(statusCode: $response->getStatusCode());
        }

        return $response->json();
    }
}
