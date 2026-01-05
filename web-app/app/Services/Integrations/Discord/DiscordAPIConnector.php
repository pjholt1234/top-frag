<?php

namespace App\Services\Integrations\Discord;

use App\Exceptions\DiscordAPIConnectorException;
use Exception;
use Illuminate\Support\Facades\Http;

class DiscordAPIConnector
{
    private ?string $baseUrl = null;

    private ?string $botToken = null;

    private const string BASE_URL = 'https://discord.com/api/v10';

    private const int TIMEOUT = 30;

    public function __construct()
    {
        $this->baseUrl = self::BASE_URL;
        $this->botToken = config('services.discord.bot_token');

        if (! $this->botToken) {
            throw DiscordAPIConnectorException::configurationError('services.discord.bot_token');
        }
    }

    /**
     * Make a GET request to the Discord API.
     *
     * @param  string  $endpoint  The API endpoint (e.g., 'guilds/{guild_id}/channels')
     * @param  array<string, mixed>  $queryParams  Query parameters
     * @return array<string, mixed>
     *
     * @throws DiscordAPIConnectorException
     */
    public function get(string $endpoint, array $queryParams = []): array
    {
        $url = $this->baseUrl.'/'.ltrim($endpoint, '/');

        try {
            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders([
                    'Authorization' => "Bot {$this->botToken}",
                    'Accept' => 'application/json',
                ])
                ->get($url, $queryParams);
        } catch (Exception $e) {
            throw DiscordAPIConnectorException::requestFailed("Network error: {$e->getMessage()}");
        }

        return $this->handleResponse($response);
    }

    /**
     * Make a POST request to the Discord API.
     *
     * @param  string  $endpoint  The API endpoint
     * @param  array<string, mixed>  $data  Request body data
     * @return array<string, mixed>
     *
     * @throws DiscordAPIConnectorException
     */
    public function post(string $endpoint, array $data = []): array
    {
        $url = $this->baseUrl.'/'.ltrim($endpoint, '/');

        try {
            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders([
                    'Authorization' => "Bot {$this->botToken}",
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post($url, $data);
        } catch (Exception $e) {
            throw DiscordAPIConnectorException::requestFailed("Network error: {$e->getMessage()}");
        }

        return $this->handleResponse($response);
    }

    /**
     * Handle the HTTP response and throw appropriate exceptions.
     *
     * @param  \Illuminate\Http\Client\Response  $response
     * @return array<string, mixed>
     *
     * @throws DiscordAPIConnectorException
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
            400 => throw DiscordAPIConnectorException::badRequest($this->getErrorMessage($response)),
            401 => throw DiscordAPIConnectorException::authenticationError($this->getErrorMessage($response)),
            403 => throw DiscordAPIConnectorException::forbidden($this->getErrorMessage($response)),
            404 => throw DiscordAPIConnectorException::notFound($this->getErrorMessage($response)),
            429 => throw DiscordAPIConnectorException::rateLimitExceeded($this->getErrorMessage($response)),
            503 => throw DiscordAPIConnectorException::serviceUnavailable($this->getErrorMessage($response)),
            default => throw DiscordAPIConnectorException::requestFailed(
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

        if (is_array($body)) {
            if (isset($body['message'])) {
                return (string) $body['message'];
            }
            if (isset($body['errors'])) {
                // Discord errors can be nested objects
                return json_encode($body['errors'], JSON_PRETTY_PRINT);
            }
        }

        return $response->body() ?: 'Unknown error';
    }
}
