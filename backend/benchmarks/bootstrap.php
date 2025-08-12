<?php

putenv('APP_ENV=phpbench');
$_ENV['APP_ENV'] = 'phpbench';

// Load Composer autoloader first
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

// Try to run migrations, but don't fail if database is not available
try {
    $kernel->call('migrate:fresh');
} catch (Exception $e) {
    // Log the error but continue with benchmarks
    error_log('Database migration failed during PHPBench bootstrap: '.$e->getMessage());
    // Continue without database setup
}
