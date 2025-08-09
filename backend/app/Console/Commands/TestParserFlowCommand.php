<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TestParserFlowCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:parser-flow 
                            {--parser-url= : The parser service URL (defaults to config value)}
                            {--api-key=top-frag-parser-api-key-2024 : API key for parser service}
                            {--file=demo.dem : Demo file to upload}
                            {--progress-callback=http://localhost:8000/api/upload/callback/progress : Progress callback URL}
                            {--completion-callback=http://localhost:8000/api/upload/callback/completion : Completion callback URL}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the complete parser flow by uploading a demo file to the parser service';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $parserUrl = $this->option('parser-url') ?: config('services.parser.base_url') . '/api/parse-demo';
        $apiKey = $this->option('api-key');
        $fileName = $this->option('file');
        $progressCallback = $this->option('progress-callback');
        $completionCallback = $this->option('completion-callback');

        $filePath = storage_path("app/public/{$fileName}");

        $this->info("=== CS:GO Demo Parser Flow Test ===");
        $this->info("Parser Service URL: {$parserUrl}");
        $this->info("Using file: {$filePath}");
        $this->info("API Key: " . ($apiKey ? 'Provided' : 'Not provided'));
        $this->info("Progress Callback: {$progressCallback}");
        $this->info("Completion Callback: {$completionCallback}");

        // Check if file exists
        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        $fileSize = filesize($filePath);
        $this->info("File size: " . number_format($fileSize / 1024 / 1024, 2) . " MB");

        // Step 1: Check parser service health
        $this->info("\n1. Checking parser service health...");
        try {
            $healthResponse = Http::timeout(10)->get(config('services.parser.base_url') . '/health');
            if ($healthResponse->successful()) {
                $healthData = $healthResponse->json();
                $this->info("Parser service is healthy");
            } else {
                $this->error("Parser service health check failed");
                $this->error("Status: " . $healthResponse->status());
                return 1;
            }
        } catch (\Exception $e) {
            $this->error("Cannot connect to parser service: " . $e->getMessage());
            return 1;
        }

        // Step 2: Upload demo file to parser service
        $this->info("\n2. Uploading demo file to parser service...");
        try {
            $this->info("Preparing upload request...");

            // Prepare the request
            $request = Http::timeout(300); // 5 minutes timeout for large files

            if ($apiKey) {
                $request->withHeaders([
                    'X-API-Key' => $apiKey,
                    'Accept' => 'application/json',
                ]);
            }

            $this->info("Sending upload request...");

            $formData = [
                'progress_callback_url' => $progressCallback,
                'completion_callback_url' => $completionCallback,
            ];

            $response = $request->attach(
                'demo_file',
                file_get_contents($filePath),
                $fileName
            )->post($parserUrl, $formData);

            $this->info("Response Status: " . $response->status());
            $this->info("Response Headers: " . json_encode($response->headers(), JSON_PRETTY_PRINT));
            $this->info("Response Body: " . $response->body());

            if ($response->successful()) {
                $this->info("Demo upload to parser service successful!");

                $responseData = $response->json();
                $jobId = $responseData['job_id'] ?? null;

                if ($jobId) {
                    $this->info("Job ID: " . $jobId);

                    // Log the successful upload
                    Log::channel('parser')->info('Parser flow test - demo uploaded successfully', [
                        'parser_url' => $parserUrl,
                        'file' => $fileName,
                        'file_size' => $fileSize,
                        'job_id' => $jobId,
                        'response_status' => $response->status(),
                        'progress_callback' => $progressCallback,
                        'completion_callback' => $completionCallback
                    ]);
                }
            } else {
                $this->error("Demo upload to parser service failed!");
                $this->error("Status Code: " . $response->status());
                $this->error("Error: " . $response->body());

                Log::channel('parser')->error('Parser flow test - demo upload failed', [
                    'parser_url' => $parserUrl,
                    'file' => $fileName,
                    'file_size' => $fileSize,
                    'response_status' => $response->status(),
                    'response_body' => $response->body()
                ]);

                return 1;
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("Exception occurred: " . $e->getMessage());

            Log::channel('parser')->error('Parser flow test - exception occurred', [
                'parser_url' => $parserUrl,
                'file' => $fileName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return 1;
        }
    }
}
