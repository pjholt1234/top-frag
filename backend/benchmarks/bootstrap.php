<?php

putenv('APP_ENV=phpbench');
$_ENV['APP_ENV'] = 'phpbench';

// Load Composer autoloader first
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->call('migrate:fresh');
