<?php

namespace Tests\Unit;

use App\Exceptions\ParserServiceConnectorException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ParserServiceConnectorExceptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_exception_automatically_logs_error()
    {
        // Arrange
        Log::shouldReceive('channel')
            ->with('parser')
            ->andReturnSelf();

        Log::shouldReceive('error')
            ->once()
            ->with('ParserServiceConnectorException occurred', \Mockery::type('array'))
            ->andReturnNull();

        // Act & Assert
        $this->expectException(ParserServiceConnectorException::class);
        $this->expectExceptionMessage('Test error message');

        throw new ParserServiceConnectorException('Test error message', 500);
    }

    public function test_static_factory_methods_create_exceptions_with_correct_messages()
    {
        // Test service unavailable
        Log::shouldReceive('channel')
            ->with('parser')
            ->andReturnSelf();

        Log::shouldReceive('error')
            ->once()
            ->andReturnNull();

        $this->expectException(ParserServiceConnectorException::class);
        $this->expectExceptionMessage('Parser service is unavailable: Custom reason');
        throw ParserServiceConnectorException::serviceUnavailable('Custom reason');
    }

    public function test_upload_failed_exception()
    {
        Log::shouldReceive('channel')
            ->with('parser')
            ->andReturnSelf();

        Log::shouldReceive('error')
            ->once()
            ->andReturnNull();

        $this->expectException(ParserServiceConnectorException::class);
        $this->expectExceptionMessage('Demo upload failed: Upload error');
        throw ParserServiceConnectorException::uploadFailed('Upload error');
    }

    public function test_configuration_error_exception()
    {
        Log::shouldReceive('channel')
            ->with('parser')
            ->andReturnSelf();

        Log::shouldReceive('error')
            ->once()
            ->andReturnNull();

        $this->expectException(ParserServiceConnectorException::class);
        $this->expectExceptionMessage('Parser service configuration error: Missing or invalid \'test.config\'');
        throw ParserServiceConnectorException::configurationError('test.config');
    }

    public function test_timeout_error_exception()
    {
        Log::shouldReceive('channel')
            ->with('parser')
            ->andReturnSelf();

        Log::shouldReceive('error')
            ->once()
            ->andReturnNull();

        $this->expectException(ParserServiceConnectorException::class);
        $this->expectExceptionMessage('Parser service request timed out after 30 seconds');
        throw ParserServiceConnectorException::timeoutError(30);
    }

    public function test_authentication_error_exception()
    {
        Log::shouldReceive('channel')
            ->with('parser')
            ->andReturnSelf();

        Log::shouldReceive('error')
            ->once()
            ->andReturnNull();

        $this->expectException(ParserServiceConnectorException::class);
        $this->expectExceptionMessage('Parser service authentication failed: Invalid key');
        throw ParserServiceConnectorException::authenticationError('Invalid key');
    }

    public function test_exception_includes_previous_exception_in_log_context()
    {
        // Arrange
        $previousException = new \Exception('Previous error', 404);

        Log::shouldReceive('channel')
            ->with('parser')
            ->andReturnSelf();

        Log::shouldReceive('error')
            ->once()
            ->with('ParserServiceConnectorException occurred', \Mockery::type('array'))
            ->andReturnNull();

        // Act & Assert
        $this->expectException(ParserServiceConnectorException::class);

        throw new ParserServiceConnectorException('Main error', 500, $previousException);
    }

    public function test_exception_logs_comprehensive_context()
    {
        // Arrange
        Log::shouldReceive('channel')
            ->with('parser')
            ->andReturnSelf();

        Log::shouldReceive('error')
            ->once()
            ->with('ParserServiceConnectorException occurred', \Mockery::on(function ($context) {
                return isset($context['exception']) &&
                    isset($context['message']) &&
                    isset($context['code']) &&
                    isset($context['file']) &&
                    isset($context['line']) &&
                    isset($context['trace']);
            }))
            ->andReturnNull();

        // Act & Assert
        $this->expectException(ParserServiceConnectorException::class);

        throw new ParserServiceConnectorException('Test message', 123);
    }
}
