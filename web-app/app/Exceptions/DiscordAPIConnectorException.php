<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;

class DiscordAPIConnectorException extends Exception
{
    /**
     * Create a new DiscordAPIConnectorException instance.
     */
    public function __construct(string $message = '', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);

        // Automatically log the error when the exception is created
        $this->logError();
    }

    /**
     * Log the error details.
     */
    private function logError(): void
    {
        $context = [
            'exception' => static::class,
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'trace' => $this->getTraceAsString(),
        ];

        // Add previous exception details if available
        if ($this->getPrevious()) {
            $context['previous_exception'] = [
                'class' => get_class($this->getPrevious()),
                'message' => $this->getPrevious()->getMessage(),
                'code' => $this->getPrevious()->getCode(),
            ];
        }

        Log::error('DiscordAPIConnectorException occurred', $context);
    }

    /**
     * Create an exception for service unavailable errors.
     */
    public static function serviceUnavailable(string $reason = 'Discord API is unavailable', int $statusCode = 503): static
    {
        return new static("Discord API service is unavailable: {$reason}", $statusCode);
    }

    /**
     * Create an exception for request failures.
     */
    public static function requestFailed(string $reason = 'Request to Discord API failed', int $statusCode = 500): static
    {
        return new static("Discord API request failed: {$reason}", $statusCode);
    }

    /**
     * Create an exception for configuration errors.
     */
    public static function configurationError(string $configKey): static
    {
        return new static("Discord API configuration error: Missing or invalid '{$configKey}'", 500);
    }

    /**
     * Create an exception for authentication errors.
     */
    public static function authenticationError(string $reason = 'Invalid bot token'): static
    {
        return new static("Discord API authentication failed: {$reason}", 401);
    }

    /**
     * Create an exception for rate limiting errors.
     */
    public static function rateLimitExceeded(string $reason = 'Rate limit exceeded'): static
    {
        return new static("Discord API rate limit exceeded: {$reason}", 429);
    }

    /**
     * Create an exception for not found errors.
     */
    public static function notFound(string $reason = 'Resource not found'): static
    {
        return new static("Discord API resource not found: {$reason}", 404);
    }

    /**
     * Create an exception for bad request errors.
     */
    public static function badRequest(string $reason = 'Bad request'): static
    {
        return new static("Discord API bad request: {$reason}", 400);
    }

    /**
     * Create an exception for forbidden errors.
     */
    public static function forbidden(string $reason = 'Access forbidden'): static
    {
        return new static("Discord API access forbidden: {$reason}", 403);
    }
}
