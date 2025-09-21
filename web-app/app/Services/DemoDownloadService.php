<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DemoDownloadService
{
    private const int MAX_DEMO_URL_RETRIES = 2;

    private const int RETRY_INTERVAL = 2000;

    private const int MAX_FILE_SIZE = 1073741824;

    private const int TIMEOUT = 300;

    private string $tempDirectory;

    public function __construct()
    {
        $this->tempDirectory = storage_path('app/temp/demos');
    }

    public function downloadDemo(string $sharecode): ?string
    {
        if (! $this->isValidSharecode($sharecode)) {
            Log::error('Invalid sharecode format', ['sharecode' => $sharecode]);

            return null;
        }

        $demoUrl = $this->fetchDemoUrl($sharecode);

        if (! $demoUrl) {
            Log::info('Demo not available yet', ['sharecode' => $sharecode]);

            return null;
        }

        $isCompressed = str_ends_with($demoUrl, '.bz2');
        $tempFilePath = $this->getTempFilePath($sharecode, $isCompressed);

        try {
            $this->ensureTempDirectoryExists();

            $response = Http::timeout(self::TIMEOUT)
                ->withOptions([
                    'sink' => $tempFilePath,
                    'progress' => function ($downloadTotal, $downloadedBytes) use ($sharecode) {
                        if ($downloadTotal > self::MAX_FILE_SIZE) {
                            Log::error('Demo file too large', [
                                'sharecode' => $sharecode,
                                'size' => $downloadTotal,
                                'max_size' => self::MAX_FILE_SIZE,
                            ]);
                            throw new Exception('File too large');
                        }
                    },
                ])
                ->get($demoUrl);

            if (! $response->successful()) {
                Log::error('Demo download failed', [
                    'sharecode' => $sharecode,
                    'status_code' => $response->getStatusCode(),
                    'response' => $response->body(),
                ]);
                $this->cleanupTempFile($tempFilePath);

                return null;
            }

            if (! file_exists($tempFilePath) || filesize($tempFilePath) === 0) {
                Log::error('Downloaded demo file is empty or missing', [
                    'sharecode' => $sharecode,
                    'temp_path' => $tempFilePath,
                ]);
                $this->cleanupTempFile($tempFilePath);

                return null;
            }

            $fileSize = filesize($tempFilePath);
            if ($fileSize > self::MAX_FILE_SIZE) {
                Log::error('Downloaded demo file exceeds size limit', [
                    'sharecode' => $sharecode,
                    'size' => $fileSize,
                    'max_size' => self::MAX_FILE_SIZE,
                ]);
                $this->cleanupTempFile($tempFilePath);

                return null;
            }

            Log::info('Demo download completed successfully', [
                'sharecode' => $sharecode,
                'file_size' => $fileSize,
                'temp_path' => $tempFilePath,
            ]);

            return $tempFilePath;
        } catch (Exception $e) {
            Log::error('Demo download exception', [
                'sharecode' => $sharecode,
                'error' => $e->getMessage(),
            ]);
            $this->cleanupTempFile($tempFilePath);

            return null;
        }
    }

    public function cleanupTempFile(string $filePath): void
    {
        if (file_exists($filePath)) {
            try {
                unlink($filePath);
                Log::info('Cleaned up temporary demo file', ['file_path' => $filePath]);
            } catch (Exception $e) {
                Log::warning('Failed to cleanup temporary demo file', [
                    'file_path' => $filePath,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function cleanupOldTempFiles(int $maxAgeHours = 24): void
    {
        if (! is_dir($this->tempDirectory)) {
            return;
        }

        $maxAge = time() - ($maxAgeHours * 3600);
        $files = array_merge(
            glob($this->tempDirectory.'/*.dem'),
            glob($this->tempDirectory.'/*.dem.bz2')
        );

        foreach ($files as $file) {
            if (filemtime($file) < $maxAge) {
                try {
                    unlink($file);
                    Log::info('Cleaned up old temporary demo file', ['file_path' => $file]);
                } catch (Exception $e) {
                    Log::warning('Failed to cleanup old temporary demo file', [
                        'file_path' => $file,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    public function fetchDemoUrl(string $sharecode): ?string
    {
        $rateLimiter = app(RateLimiterService::class);

        if (! $rateLimiter->checkValveDemoUrlLimit()) {
            Log::warning('Valve demo URL service rate limit reached, waiting');
            $rateLimiter->waitForRateLimit('valve_demo_url', 20, 60);
        }

        $baseUrl = config('services.valve_demo_url_service.base_url');
        $apiKey = config('services.valve_demo_url_service.api_key');

        if (! $baseUrl || ! $apiKey) {
            Log::error('Valve demo URL service configuration missing', [
                'sharecode' => $sharecode,
                'base_url' => $baseUrl,
                'api_key_configured' => ! empty($apiKey),
            ]);

            return null;
        }

        try {
            Log::info('Requesting demo URL from valve-demo-url-service', [
                'sharecode' => $sharecode,
                'service_url' => $baseUrl,
            ]);

            $response = Http::timeout(60)
                ->retry(self::MAX_DEMO_URL_RETRIES, self::RETRY_INTERVAL)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-API-Key' => $apiKey,
                ])
                ->post("{$baseUrl}/demo", [
                    'sharecode' => $sharecode,
                ]);

            if (! $response->successful()) {
                Log::error('Valve demo URL service request failed', [
                    'sharecode' => $sharecode,
                    'status_code' => $response->getStatusCode(),
                    'response_body' => $response->body(),
                ]);

                return null;
            }

            $data = $response->json();

            if (! isset($data['demoUrl']) || empty($data['demoUrl'])) {
                Log::warning('Valve demo URL service returned no demo URL', [
                    'sharecode' => $sharecode,
                    'response_data' => $data,
                ]);

                return null;
            }

            Log::info('Successfully retrieved demo URL from valve-demo-url-service', [
                'sharecode' => $sharecode,
                'demo_url' => $data['demoUrl'],
                'service' => $data['service'] ?? 'unknown',
            ]);

            return $data['demoUrl'];
        } catch (Exception $e) {
            Log::error('Exception while calling valve-demo-url-service', [
                'sharecode' => $sharecode,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    private function getTempFilePath(string $sharecode, bool $isCompressed = false): string
    {
        $extension = $isCompressed ? '.dem.bz2' : '.dem';
        $filename = $sharecode.$extension;

        return $this->tempDirectory.'/'.$filename;
    }

    private function ensureTempDirectoryExists(): void
    {
        if (! is_dir($this->tempDirectory)) {
            mkdir($this->tempDirectory, 0755, true);
        }
    }

    private function isValidSharecode(string $sharecode): bool
    {
        $pattern = '/^CSGO-[A-Za-z0-9]{5}-[A-Za-z0-9]{5}-[A-Za-z0-9]{5}-[A-Za-z0-9]{5}-[A-Za-z0-9]{5}$/';

        return preg_match($pattern, $sharecode) === 1;
    }
}
