<?php

namespace App\Services\Integrations\FaceIT;

use App\Exceptions\FaceITAPIConnectorException;
use Exception;
use Illuminate\Support\Facades\Http;

class FaceITAPIConnector
{
    private ?string $baseUrl = null;

    private ?string $apiKey = null;

    private const string BASE_URL = 'https://open.faceit.com/data/v4';

    private const int TIMEOUT = 30;

    public function __construct()
    {
        $this->baseUrl = self::BASE_URL;
        $this->apiKey = config('services.faceit.api_key');

        if (! $this->apiKey) {
            throw FaceITAPIConnectorException::configurationError('services.faceit.api_key');
        }
    }

    /**
     * Make a GET request to the FACEIT API.
     *
     * @param  string  $endpoint  The API endpoint (e.g., 'players')
     * @param  array<string, mixed>  $queryParams  Query parameters
     * @return array<string, mixed>
     *
     * @throws FaceITAPIConnectorException
     */
    public function get(string $endpoint, array $queryParams = []): array
    {
        $url = $this->baseUrl.'/'.ltrim($endpoint, '/');

        try {
            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Accept' => 'application/json',
                ])
                ->get($url, $queryParams);
        } catch (Exception $e) {
            throw FaceITAPIConnectorException::requestFailed("Network error: {$e->getMessage()}");
        }

        return $this->handleResponse($response);
    }

    /**
     * Handle the HTTP response and throw appropriate exceptions.
     *
     * @param  \Illuminate\Http\Client\Response  $response
     * @return array<string, mixed>
     *
     * @throws FaceITAPIConnectorException
     */
    private function handleResponse($response): array
    {
        $statusCode = $response->status();

        if ($response->successful()) {
            $data = $response->json();

            return is_array($data) ? $data : [];
        }

        // Handle specific HTTP status codes
        match ($statusCode) {
            400 => throw FaceITAPIConnectorException::badRequest($this->getErrorMessage($response)),
            401 => throw FaceITAPIConnectorException::authenticationError($this->getErrorMessage($response)),
            403 => throw FaceITAPIConnectorException::authenticationError('Access forbidden'),
            404 => throw FaceITAPIConnectorException::notFound($this->getErrorMessage($response)),
            429 => throw FaceITAPIConnectorException::rateLimitExceeded($this->getErrorMessage($response)),
            503 => throw FaceITAPIConnectorException::serviceUnavailable($this->getErrorMessage($response)),
            default => throw FaceITAPIConnectorException::requestFailed(
                $this->getErrorMessage($response),
                $statusCode
            ),
        };
    }

    /**
     * Extract error message from response.
     */
    private function getErrorMessage($response): string
    {
        $body = $response->json();

        if (is_array($body) && isset($body['errors'])) {
            if (is_array($body['errors'])) {
                return implode(', ', array_map(fn ($error) => is_array($error) ? ($error['message'] ?? '') : $error, $body['errors']));
            }

            return (string) $body['errors'];
        }

        if (is_array($body) && isset($body['message'])) {
            return (string) $body['message'];
        }

        return $response->body() ?: 'Unknown error';
    }
}
