<?php

namespace Tests\Feature\Exceptions;

use App\Exceptions\DiscordAPIConnectorException;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class DiscordAPIConnectorExceptionTest extends TestCase
{
    public function test_exception_automatically_logs_error(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->with('DiscordAPIConnectorException occurred', \Mockery::type('array'))
            ->andReturnNull();

        $this->expectException(DiscordAPIConnectorException::class);
        $this->expectExceptionMessage('Test error message');

        throw new DiscordAPIConnectorException('Test error message', 500);
    }

    public function test_service_unavailable_exception(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->andReturnNull();

        $this->expectException(DiscordAPIConnectorException::class);
        $this->expectExceptionMessage('Discord API service is unavailable: Custom reason');

        throw DiscordAPIConnectorException::serviceUnavailable('Custom reason');
    }

    public function test_request_failed_exception(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->andReturnNull();

        $this->expectException(DiscordAPIConnectorException::class);
        $this->expectExceptionMessage('Discord API request failed: Request error');

        throw DiscordAPIConnectorException::requestFailed('Request error');
    }

    public function test_configuration_error_exception(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->andReturnNull();

        $this->expectException(DiscordAPIConnectorException::class);
        $this->expectExceptionMessage('Discord API configuration error: Missing or invalid \'test.config\'');

        throw DiscordAPIConnectorException::configurationError('test.config');
    }

    public function test_authentication_error_exception(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->andReturnNull();

        $this->expectException(DiscordAPIConnectorException::class);
        $this->expectExceptionMessage('Discord API authentication failed: Invalid key');

        throw DiscordAPIConnectorException::authenticationError('Invalid key');
    }

    public function test_rate_limit_exceeded_exception(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->andReturnNull();

        $this->expectException(DiscordAPIConnectorException::class);
        $this->expectExceptionMessage('Discord API rate limit exceeded: Too many requests');

        throw DiscordAPIConnectorException::rateLimitExceeded('Too many requests');
    }

    public function test_not_found_exception(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->andReturnNull();

        $this->expectException(DiscordAPIConnectorException::class);
        $this->expectExceptionMessage('Discord API resource not found: Channel not found');

        throw DiscordAPIConnectorException::notFound('Channel not found');
    }

    public function test_bad_request_exception(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->andReturnNull();

        $this->expectException(DiscordAPIConnectorException::class);
        $this->expectExceptionMessage('Discord API bad request: Invalid parameter');

        throw DiscordAPIConnectorException::badRequest('Invalid parameter');
    }

    public function test_forbidden_exception(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->andReturnNull();

        $this->expectException(DiscordAPIConnectorException::class);
        $this->expectExceptionMessage('Discord API access forbidden: Access denied');

        throw DiscordAPIConnectorException::forbidden('Access denied');
    }

    public function test_exception_includes_previous_exception_in_log_context(): void
    {
        $previousException = new \Exception('Previous error', 404);

        Log::shouldReceive('error')
            ->once()
            ->with('DiscordAPIConnectorException occurred', \Mockery::on(function ($context) {
                return isset($context['exception']) &&
                    isset($context['message']) &&
                    isset($context['code']) &&
                    isset($context['file']) &&
                    isset($context['line']) &&
                    isset($context['trace']) &&
                    isset($context['previous_exception']);
            }))
            ->andReturnNull();

        $this->expectException(DiscordAPIConnectorException::class);

        throw new DiscordAPIConnectorException('Main error', 500, $previousException);
    }

    public function test_exception_logs_comprehensive_context(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->with('DiscordAPIConnectorException occurred', \Mockery::on(function ($context) {
                return isset($context['exception']) &&
                    isset($context['message']) &&
                    isset($context['code']) &&
                    isset($context['file']) &&
                    isset($context['line']) &&
                    isset($context['trace']);
            }))
            ->andReturnNull();

        $this->expectException(DiscordAPIConnectorException::class);

        throw new DiscordAPIConnectorException('Test message', 123);
    }
}
