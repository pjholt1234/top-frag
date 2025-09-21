<?php

namespace Tests\Unit\Services;

use App\Services\RateLimiterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class RateLimiterServiceTest extends TestCase
{
    use RefreshDatabase;

    private RateLimiterService $rateLimiter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rateLimiter = new RateLimiterService;
    }

    public function test_check_steam_api_limit_allows_requests_within_limit(): void
    {
        Redis::shouldReceive('get')
            ->with('rate_limit:steam_api')
            ->andReturn('50');
        Redis::shouldReceive('incr')
            ->with('rate_limit:steam_api')
            ->andReturn(51);
        Redis::shouldReceive('expire')
            ->with('rate_limit:steam_api', 300)
            ->andReturn(true);

        $result = $this->rateLimiter->checkSteamApiLimit();

        $this->assertTrue($result);
    }

    public function test_check_steam_api_limit_blocks_requests_over_limit(): void
    {
        Redis::shouldReceive('get')
            ->with('rate_limit:steam_api')
            ->andReturn('100');

        $result = $this->rateLimiter->checkSteamApiLimit();

        $this->assertFalse($result);
    }

    public function test_check_valve_demo_url_limit_allows_requests_within_limit(): void
    {
        Redis::shouldReceive('get')
            ->with('rate_limit:valve_demo_url')
            ->andReturn('10');
        Redis::shouldReceive('incr')
            ->with('rate_limit:valve_demo_url')
            ->andReturn(11);
        Redis::shouldReceive('expire')
            ->with('rate_limit:valve_demo_url', 60)
            ->andReturn(true);

        $result = $this->rateLimiter->checkValveDemoUrlLimit();

        $this->assertTrue($result);
    }

    public function test_check_parser_service_limit_allows_requests_within_limit(): void
    {
        Redis::shouldReceive('get')
            ->with('rate_limit:parser_service')
            ->andReturn('2');

        $result = $this->rateLimiter->checkParserServiceLimit();

        $this->assertTrue($result);
    }

    public function test_check_parser_service_limit_blocks_requests_over_limit(): void
    {
        Redis::shouldReceive('get')
            ->with('rate_limit:parser_service')
            ->andReturn('3');

        $result = $this->rateLimiter->checkParserServiceLimit();

        $this->assertFalse($result);
    }

    public function test_increment_parser_service_usage(): void
    {
        Redis::shouldReceive('incr')
            ->with('rate_limit:parser_service')
            ->andReturn(1);
        Redis::shouldReceive('expire')
            ->with('rate_limit:parser_service', 300)
            ->andReturn(true);

        $this->rateLimiter->incrementParserServiceUsage();
    }

    public function test_decrement_parser_service_usage(): void
    {
        Redis::shouldReceive('get')
            ->with('rate_limit:parser_service')
            ->andReturn('2');
        Redis::shouldReceive('decr')
            ->with('rate_limit:parser_service')
            ->andReturn(1);

        $this->rateLimiter->decrementParserServiceUsage();
    }

    public function test_decrement_parser_service_usage_does_not_go_below_zero(): void
    {
        Redis::shouldReceive('get')
            ->with('rate_limit:parser_service')
            ->andReturn('0');

        $this->rateLimiter->decrementParserServiceUsage();
    }
}
