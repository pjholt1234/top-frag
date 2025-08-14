<?php

namespace Tests\Traits;

use Illuminate\Support\Facades\Log;

/**
 * Trait to mock file logging operations during tests.
 *
 * This trait prevents actual file writing during tests by:
 * 1. Mocking the Log facade
 * 2. Creating a test-specific storage path
 * 3. Cleaning up any files that might be created
 */
trait MocksFileLogging
{
    protected function mockFileLogging(): void
    {
        // Mock the Log facade and the parser channel
        Log::shouldReceive('info')->withAnyArgs()->zeroOrMoreTimes();
        Log::shouldReceive('error')->withAnyArgs()->zeroOrMoreTimes();
        Log::shouldReceive('warning')->withAnyArgs()->zeroOrMoreTimes();
        Log::shouldReceive('debug')->withAnyArgs()->zeroOrMoreTimes();

        // Mock the parser channel specifically
        Log::shouldReceive('channel')->with('parser')->andReturnSelf()->zeroOrMoreTimes();
    }

    protected function cleanupLogFiles(): void
    {
        // Clean up test log files
        $logFiles = [
            storage_path('logs/parser.log'),
        ];

        foreach ($logFiles as $logFile) {
            if (file_exists($logFile)) {
                unlink($logFile);
            }
        }
    }
}
