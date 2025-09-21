<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class RateLimiterService
{
    public function checkSteamApiLimit(): bool
    {
        return $this->checkRateLimit('steam_api', 100, 300);
    }

    public function checkValveDemoUrlLimit(): bool
    {
        return $this->checkRateLimit('valve_demo_url', 20, 60);
    }

    public function checkParserServiceLimit(): bool
    {
        $key = 'rate_limit:parser_service';
        $current = Redis::get($key) ?? 0;
        $maxConcurrent = config('services.parser.max_concurrent_jobs', 3);

        return (int) $current < $maxConcurrent;
    }

    public function checkRateLimit(string $service, int $maxRequests, int $windowSeconds): bool
    {
        $key = "rate_limit:{$service}";
        $current = Redis::get($key) ?? 0;

        if ((int) $current >= $maxRequests) {
            Log::warning('Rate limit exceeded', [
                'service' => $service,
                'current' => $current,
                'max' => $maxRequests,
            ]);

            return false;
        }

        // Increment counter
        Redis::incr($key);
        Redis::expire($key, $windowSeconds);

        return true;
    }

    public function waitForRateLimit(string $service, int $maxRequests, int $windowSeconds): void
    {
        while (! $this->checkRateLimit($service, $maxRequests, $windowSeconds)) {
            sleep(1);
        }
    }

    public function incrementParserServiceUsage(): void
    {
        $key = 'rate_limit:parser_service';
        Redis::incr($key);
        Redis::expire($key, 300);
    }

    public function decrementParserServiceUsage(): void
    {
        $key = 'rate_limit:parser_service';
        $current = Redis::get($key) ?? 0;
        if ((int) $current > 0) {
            Redis::decr($key);
        }
    }
}
