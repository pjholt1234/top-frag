<?php

namespace Tests\Unit;

use App\Exceptions\FaceITAPIConnectorException;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class FaceITAPIConnectorExceptionTest extends TestCase
{
    public function test_exception_automatically_logs_error(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->with('FaceITAPIConnectorException occurred', \Mockery::type('array'))
            ->andReturnNull();

        $this->expectException(FaceITAPIConnectorException::class);
        $this->expectExceptionMessage('Test error message');

        throw new FaceITAPIConnectorException('Test error message', 500);
    }

    public function test_service_unavailable_exception(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->andReturnNull();

        $this->expectException(FaceITAPIConnectorException::class);
        $this->expectExceptionMessage('FACEIT API service is unavailable: Custom reason');

        throw FaceITAPIConnectorException::serviceUnavailable('Custom reason');
    }

    public function test_request_failed_exception(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->andReturnNull();

        $this->expectException(FaceITAPIConnectorException::class);
        $this->expectExceptionMessage('FACEIT API request failed: Request error');

        throw FaceITAPIConnectorException::requestFailed('Request error');
    }

    public function test_configuration_error_exception(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->andReturnNull();

        $this->expectException(FaceITAPIConnectorException::class);
        $this->expectExceptionMessage('FACEIT API configuration error: Missing or invalid \'test.config\'');

        throw FaceITAPIConnectorException::configurationError('test.config');
    }

    public function test_timeout_error_exception(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->andReturnNull();

        $this->expectException(FaceITAPIConnectorException::class);
        $this->expectExceptionMessage('FACEIT API request timed out after 30 seconds');

        throw FaceITAPIConnectorException::timeoutError(30);
    }

    public function test_authentication_error_exception(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->andReturnNull();

        $this->expectException(FaceITAPIConnectorException::class);
        $this->expectExceptionMessage('FACEIT API authentication failed: Invalid key');

        throw FaceITAPIConnectorException::authenticationError('Invalid key');
    }

    public function test_rate_limit_exceeded_exception(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->andReturnNull();

        $this->expectException(FaceITAPIConnectorException::class);
        $this->expectExceptionMessage('FACEIT API rate limit exceeded: Too many requests');

        throw FaceITAPIConnectorException::rateLimitExceeded('Too many requests');
    }

    public function test_not_found_exception(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->andReturnNull();

        $this->expectException(FaceITAPIConnectorException::class);
        $this->expectExceptionMessage('FACEIT API resource not found: Player not found');

        throw FaceITAPIConnectorException::notFound('Player not found');
    }

    public function test_bad_request_exception(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->andReturnNull();

        $this->expectException(FaceITAPIConnectorException::class);
        $this->expectExceptionMessage('FACEIT API bad request: Invalid parameter');

        throw FaceITAPIConnectorException::badRequest('Invalid parameter');
    }

    public function test_exception_includes_previous_exception_in_log_context(): void
    {
        $previousException = new \Exception('Previous error', 404);

        Log::shouldReceive('error')
            ->once()
            ->with('FaceITAPIConnectorException occurred', \Mockery::on(function ($context) {
                return isset($context['exception']) &&
                    isset($context['message']) &&
                    isset($context['code']) &&
                    isset($context['file']) &&
                    isset($context['line']) &&
                    isset($context['trace']) &&
                    isset($context['previous_exception']);
            }))
            ->andReturnNull();

        $this->expectException(FaceITAPIConnectorException::class);

        throw new FaceITAPIConnectorException('Main error', 500, $previousException);
    }

    public function test_exception_logs_comprehensive_context(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->with('FaceITAPIConnectorException occurred', \Mockery::on(function ($context) {
                return isset($context['exception']) &&
                    isset($context['message']) &&
                    isset($context['code']) &&
                    isset($context['file']) &&
                    isset($context['line']) &&
                    isset($context['trace']);
            }))
            ->andReturnNull();

        $this->expectException(FaceITAPIConnectorException::class);

        throw new FaceITAPIConnectorException('Test message', 123);
    }
}
