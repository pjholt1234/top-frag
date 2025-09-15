<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class MatchCacheManager
{
    private const int CACHE_TTL = 1800; // 30 minutes

    /**
     * Check if caching is enabled via environment variable
     */
    private static function isCacheEnabled(): bool
    {
        return config('app.cache_enabled', true);
    }

    /**
     * Cache a match component
     */
    public static function cache(string $component, int $matchId, array $data, ?int $ttl = null): void
    {
        if (! self::isCacheEnabled()) {
            return;
        }

        $key = self::getKey($component, $matchId);
        Cache::put($key, $data, $ttl ?? self::CACHE_TTL);
    }

    /**
     * Get cached match component
     */
    public static function get(string $component, int $matchId): ?array
    {
        if (! self::isCacheEnabled()) {
            return null;
        }

        $key = self::getKey($component, $matchId);

        return Cache::get($key);
    }

    /**
     * Remember with callback
     */
    public static function remember(string $component, int $matchId, callable $callback, ?int $ttl = null): array
    {
        if (! self::isCacheEnabled()) {
            return $callback();
        }

        $key = self::getKey($component, $matchId);

        return Cache::remember($key, $ttl ?? self::CACHE_TTL, $callback);
    }

    /**
     * Invalidate specific component
     */
    public static function invalidateComponent(int $matchId, string $component): void
    {
        if (! self::isCacheEnabled()) {
            return;
        }

        $key = self::getKey($component, $matchId);
        Cache::forget($key);

        // Also invalidate the complete match since it depends on components
        self::invalidateComplete($matchId);
    }

    /**
     * Invalidate complete match (all components)
     */
    public static function invalidateComplete(int $matchId): void
    {
        if (! self::isCacheEnabled()) {
            return;
        }

        $components = ['match-details', 'player-stats', 'utility-analysis', 'grenade-explorer', 'head-to-head'];

        foreach ($components as $component) {
            Cache::forget(self::getKey($component, $matchId));
        }
    }

    /**
     * Invalidate all match data (when match is updated)
     */
    public static function invalidateAll(int $matchId): void
    {
        if (! self::isCacheEnabled()) {
            return;
        }

        self::invalidateComplete($matchId);

        // Also invalidate the old match_data_{id} key for backward compatibility
        Cache::forget("match_data_{$matchId}");
    }

    /**
     * Check if cached data exists
     */
    public static function has(string $component, int $matchId): bool
    {
        if (! self::isCacheEnabled()) {
            return false;
        }

        $key = self::getKey($component, $matchId);

        return Cache::has($key);
    }

    /**
     * Generate cache key
     */
    private static function getKey(string $component, int $matchId): string
    {
        return "match_{$matchId}_component_{$component}";
    }
}
