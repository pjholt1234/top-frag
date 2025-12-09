<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up test configuration for services that require API keys
        config([
            'services.faceit.api_key' => 'test-api-key',
            'services.steam.api_key' => 'test-steam-api-key',
        ]);
    }
}
