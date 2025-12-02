<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;

class FaceITAPIConnectorException extends Exception
{
    /**
     * Create a new FaceITAPIConnectorException instance.
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

        Log::error('FaceITAPIConnectorException occurred', $context);
    }

    /**
     * Create an exception for service unavailable errors.
     */
    public static function serviceUnavailable(string $reason = 'FACEIT API is unavailable', int $statusCode = 503): static
    {
        return new static("FACEIT API service is unavailable: {$reason}", $statusCode);
    }

    /**
     * Create an exception for request failures.
     */
    public static function requestFailed(string $reason = 'Request to FACEIT API failed', int $statusCode = 500): static
    {
        return new static("FACEIT API request failed: {$reason}", $statusCode);
    }

    /**
     * Create an exception for configuration errors.
     */
    public static function configurationError(string $configKey): static
    {
        return new static("FACEIT API configuration error: Missing or invalid '{$configKey}'", 500);
    }

    /**
     * Create an exception for timeout errors.
     */
    public static function timeoutError(int $timeout): static
    {
        return new static("FACEIT API request timed out after {$timeout} seconds", 408);
    }

    /**
     * Create an exception for authentication errors.
     */
    public static function authenticationError(string $reason = 'Invalid API key'): static
    {
        return new static("FACEIT API authentication failed: {$reason}", 401);
    }

    /**
     * Create an exception for rate limiting errors.
     */
    public static function rateLimitExceeded(string $reason = 'Rate limit exceeded'): static
    {
        return new static("FACEIT API rate limit exceeded: {$reason}", 429);
    }

    /**
     * Create an exception for not found errors.
     */
    public static function notFound(string $reason = 'Resource not found'): static
    {
        return new static("FACEIT API resource not found: {$reason}", 404);
    }

    /**
     * Create an exception for bad request errors.
     */
    public static function badRequest(string $reason = 'Bad request'): static
    {
        return new static("FACEIT API bad request: {$reason}", 400);
    }
}
