<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;

class ParserServiceConnectorException extends Exception
{
    /**
     * Create a new ParserServiceConnectorException instance.
     *
     * @param string $message
     * @param int $code
     * @param Exception|null $previous
     */
    public function __construct(string $message = "", int $code = 0)
    {
        parent::__construct($message, $code);

        // Automatically log the error when the exception is created
        $this->logError();
    }

    /**
     * Log the error details to the parser channel.
     *
     * @return void
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

        Log::channel('parser')->error('ParserServiceConnectorException occurred', $context);
    }

    /**
     * Create an exception for service health check failures.
     *
     * @param string $reason
     * @param int $statusCode
     * @return static
     */
    public static function serviceUnavailable(string $reason = "Service health check failed", int $statusCode = 503): static
    {
        return new static("Parser service is unavailable: {$reason}", $statusCode);
    }

    /**
     * Create an exception for upload failures.
     *
     * @param string $reason
     * @param int $statusCode
     * @return static
     */
    public static function uploadFailed(string $reason = "Demo upload failed", int $statusCode = 500): static
    {
        return new static("Demo upload failed: {$reason}", $statusCode);
    }

    /**
     * Create an exception for configuration errors.
     *
     * @param string $configKey
     * @return static
     */
    public static function configurationError(string $configKey): static
    {
        return new static("Parser service configuration error: Missing or invalid '{$configKey}'", 500);
    }

    /**
     * Create an exception for timeout errors.
     *
     * @param int $timeout
     * @return static
     */
    public static function timeoutError(int $timeout): static
    {
        return new static("Parser service request timed out after {$timeout} seconds", 408);
    }

    /**
     * Create an exception for authentication errors.
     *
     * @param string $reason
     * @return static
     */
    public static function authenticationError(string $reason = "Invalid API key"): static
    {
        return new static("Parser service authentication failed: {$reason}", 401);
    }
}
