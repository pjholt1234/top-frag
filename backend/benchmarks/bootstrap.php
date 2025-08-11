<?php

putenv('APP_ENV=phpbench');
$_ENV['APP_ENV'] = 'phpbench';

// Load Composer autoloader first
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

// Disable Laravel error handler if requested (for PHPBench compatibility)
if (getenv('LARAVEL_DISABLE_ERROR_HANDLER') === '1') {
    // Disable Laravel's error handler to prevent it from catching PHPBench reflection errors
    restore_error_handler();
    restore_exception_handler();
}

// Try to run migrations, but don't fail if database is not available
try {
    $kernel->call('migrate:fresh');
} catch (Exception $e) {
    // Log the error but continue with benchmarks
    error_log("Database migration failed during PHPBench bootstrap: " . $e->getMessage());
    // Continue without database setup
}
