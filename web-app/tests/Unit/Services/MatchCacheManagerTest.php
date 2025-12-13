<?php

namespace Tests\Unit\Services;

use App\Services\Infrastructure\MatchCacheManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class MatchCacheManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Clear cache before each test
        Cache::flush();
    }

    public function test_cache_stores_data_when_cache_enabled()
    {
        Config::set('app.cache_enabled', true);

        $component = 'test-component';
        $matchId = 123;
        $data = ['key' => 'value'];
        $ttl = 3600;

        MatchCacheManager::cache($component, $matchId, $data, $ttl);

        $cachedData = Cache::get("match_{$matchId}_component_{$component}");
        $this->assertEquals($data, $cachedData);
    }

    public function test_cache_does_not_store_when_cache_disabled()
    {
        Config::set('app.cache_enabled', false);

        $component = 'test-component';
        $matchId = 123;
        $data = ['key' => 'value'];

        MatchCacheManager::cache($component, $matchId, $data);

        $cachedData = Cache::get("match_{$matchId}_component_{$component}");
        $this->assertNull($cachedData);
    }

    public function test_get_returns_cached_data_when_cache_enabled()
    {
        Config::set('app.cache_enabled', true);

        $component = 'test-component';
        $matchId = 123;
        $data = ['key' => 'value'];

        // Manually store data in cache
        Cache::put("match_{$matchId}_component_{$component}", $data, 3600);

        $result = MatchCacheManager::get($component, $matchId);

        $this->assertEquals($data, $result);
    }

    public function test_get_returns_null_when_cache_disabled()
    {
        Config::set('app.cache_enabled', false);

        $component = 'test-component';
        $matchId = 123;

        $result = MatchCacheManager::get($component, $matchId);

        $this->assertNull($result);
    }

    public function test_get_returns_null_when_data_not_cached()
    {
        Config::set('app.cache_enabled', true);

        $component = 'non-existent-component';
        $matchId = 999;

        $result = MatchCacheManager::get($component, $matchId);

        $this->assertNull($result);
    }

    public function test_remember_executes_callback_when_cache_disabled()
    {
        Config::set('app.cache_enabled', false);

        $component = 'test-component';
        $matchId = 123;
        $callbackExecuted = false;

        $result = MatchCacheManager::remember($component, $matchId, function () use (&$callbackExecuted) {
            $callbackExecuted = true;

            return ['data' => 'from callback'];
        });

        $this->assertTrue($callbackExecuted);
        $this->assertEquals(['data' => 'from callback'], $result);
    }

    public function test_remember_uses_cache_when_enabled()
    {
        Config::set('app.cache_enabled', true);

        $component = 'test-component';
        $matchId = 123;
        $callbackExecuted = false;

        // First call should execute callback
        $result1 = MatchCacheManager::remember($component, $matchId, function () use (&$callbackExecuted) {
            $callbackExecuted = true;

            return ['data' => 'from callback'];
        });

        $this->assertTrue($callbackExecuted);
        $this->assertEquals(['data' => 'from callback'], $result1);

        // Reset callback flag
        $callbackExecuted = false;

        // Second call should use cache, not execute callback
        $result2 = MatchCacheManager::remember($component, $matchId, function () use (&$callbackExecuted) {
            $callbackExecuted = true;

            return ['data' => 'from callback again'];
        });

        $this->assertFalse($callbackExecuted);
        $this->assertEquals(['data' => 'from callback'], $result2);
    }

    public function test_remember_with_custom_ttl()
    {
        Config::set('app.cache_enabled', true);

        $component = 'test-component';
        $matchId = 123;
        $customTtl = 7200; // 2 hours

        $result = MatchCacheManager::remember($component, $matchId, function () {
            return ['data' => 'from callback'];
        }, $customTtl);

        $this->assertEquals(['data' => 'from callback'], $result);

        // Verify data is cached
        $cachedData = Cache::get("match_{$matchId}_component_{$component}");
        $this->assertEquals(['data' => 'from callback'], $cachedData);
    }

    public function test_invalidate_component_removes_specific_component()
    {
        Config::set('app.cache_enabled', true);

        $matchId = 123;
        $component1 = 'component1';
        $component2 = 'component2';
        $data1 = ['data1'];
        $data2 = ['data2'];

        // Cache both components
        MatchCacheManager::cache($component1, $matchId, $data1);
        MatchCacheManager::cache($component2, $matchId, $data2);

        // Verify both are cached
        $this->assertEquals($data1, MatchCacheManager::get($component1, $matchId));
        $this->assertEquals($data2, MatchCacheManager::get($component2, $matchId));

        // Invalidate only component1
        MatchCacheManager::invalidateComponent($matchId, $component1);

        // component1 should be gone, component2 should remain
        $this->assertNull(MatchCacheManager::get($component1, $matchId));
        $this->assertEquals($data2, MatchCacheManager::get($component2, $matchId));
    }

    public function test_invalidate_component_does_nothing_when_cache_disabled()
    {
        Config::set('app.cache_enabled', false);

        $matchId = 123;
        $component = 'test-component';

        // Should not throw any exceptions
        MatchCacheManager::invalidateComponent($matchId, $component);

        $this->assertTrue(true); // Test passes if no exception is thrown
    }

    public function test_invalidate_complete_removes_all_components()
    {
        Config::set('app.cache_enabled', true);

        $matchId = 123;
        $components = ['match-details', 'player-stats', 'utility-analysis', 'grenade-explorer', 'head-to-head'];

        // Cache all components
        foreach ($components as $component) {
            MatchCacheManager::cache($component, $matchId, ['data' => $component]);
        }

        // Verify all are cached
        foreach ($components as $component) {
            $this->assertNotNull(MatchCacheManager::get($component, $matchId));
        }

        // Invalidate complete match
        MatchCacheManager::invalidateComplete($matchId);

        // All components should be gone
        foreach ($components as $component) {
            $this->assertNull(MatchCacheManager::get($component, $matchId));
        }
    }

    public function test_invalidate_complete_does_nothing_when_cache_disabled()
    {
        Config::set('app.cache_enabled', false);

        $matchId = 123;

        // Should not throw any exceptions
        MatchCacheManager::invalidateComplete($matchId);

        $this->assertTrue(true); // Test passes if no exception is thrown
    }

    public function test_invalidate_all_removes_complete_match_and_legacy_key()
    {
        Config::set('app.cache_enabled', true);

        $matchId = 123;
        $components = ['match-details', 'player-stats', 'utility-analysis', 'grenade-explorer', 'head-to-head'];

        // Cache all components
        foreach ($components as $component) {
            MatchCacheManager::cache($component, $matchId, ['data' => $component]);
        }

        // Cache legacy key
        Cache::put("match_data_{$matchId}", ['legacy' => 'data'], 3600);

        // Verify all are cached
        foreach ($components as $component) {
            $this->assertNotNull(MatchCacheManager::get($component, $matchId));
        }
        $this->assertNotNull(Cache::get("match_data_{$matchId}"));

        // Invalidate all
        MatchCacheManager::invalidateAll($matchId);

        // All components and legacy key should be gone
        foreach ($components as $component) {
            $this->assertNull(MatchCacheManager::get($component, $matchId));
        }
        $this->assertNull(Cache::get("match_data_{$matchId}"));
    }

    public function test_invalidate_all_does_nothing_when_cache_disabled()
    {
        Config::set('app.cache_enabled', false);

        $matchId = 123;

        // Should not throw any exceptions
        MatchCacheManager::invalidateAll($matchId);

        $this->assertTrue(true); // Test passes if no exception is thrown
    }

    public function test_has_returns_true_when_data_exists()
    {
        Config::set('app.cache_enabled', true);

        $component = 'test-component';
        $matchId = 123;
        $data = ['key' => 'value'];

        MatchCacheManager::cache($component, $matchId, $data);

        $this->assertTrue(MatchCacheManager::has($component, $matchId));
    }

    public function test_has_returns_false_when_data_does_not_exist()
    {
        Config::set('app.cache_enabled', true);

        $component = 'non-existent-component';
        $matchId = 999;

        $this->assertFalse(MatchCacheManager::has($component, $matchId));
    }

    public function test_has_returns_false_when_cache_disabled()
    {
        Config::set('app.cache_enabled', false);

        $component = 'test-component';
        $matchId = 123;

        $this->assertFalse(MatchCacheManager::has($component, $matchId));
    }

    public function test_cache_key_generation()
    {
        Config::set('app.cache_enabled', true);

        $component = 'test-component';
        $matchId = 123;
        $expectedKey = "match_{$matchId}_component_{$component}";

        MatchCacheManager::cache($component, $matchId, ['data']);

        $this->assertTrue(Cache::has($expectedKey));
    }

    public function test_different_match_ids_use_different_keys()
    {
        Config::set('app.cache_enabled', true);

        $component = 'test-component';
        $matchId1 = 123;
        $matchId2 = 456;
        $data1 = ['data1'];
        $data2 = ['data2'];

        MatchCacheManager::cache($component, $matchId1, $data1);
        MatchCacheManager::cache($component, $matchId2, $data2);

        $this->assertEquals($data1, MatchCacheManager::get($component, $matchId1));
        $this->assertEquals($data2, MatchCacheManager::get($component, $matchId2));
    }

    public function test_different_components_use_different_keys()
    {
        Config::set('app.cache_enabled', true);

        $component1 = 'component1';
        $component2 = 'component2';
        $matchId = 123;
        $data1 = ['data1'];
        $data2 = ['data2'];

        MatchCacheManager::cache($component1, $matchId, $data1);
        MatchCacheManager::cache($component2, $matchId, $data2);

        $this->assertEquals($data1, MatchCacheManager::get($component1, $matchId));
        $this->assertEquals($data2, MatchCacheManager::get($component2, $matchId));
    }

    public function test_default_ttl_is_used_when_not_specified()
    {
        Config::set('app.cache_enabled', true);

        $component = 'test-component';
        $matchId = 123;
        $data = ['key' => 'value'];

        MatchCacheManager::cache($component, $matchId, $data);

        // Verify data is cached (we can't easily test the exact TTL without mocking)
        $this->assertNotNull(MatchCacheManager::get($component, $matchId));
    }

    public function test_remember_callback_exception_handling()
    {
        Config::set('app.cache_enabled', true);

        $component = 'test-component';
        $matchId = 123;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Callback exception');

        MatchCacheManager::remember($component, $matchId, function () {
            throw new \Exception('Callback exception');
        });
    }

    public function test_complex_data_structures_can_be_cached()
    {
        Config::set('app.cache_enabled', true);

        $component = 'complex-component';
        $matchId = 123;
        $complexData = [
            'nested' => [
                'array' => [1, 2, 3],
                'object' => (object) ['key' => 'value'],
            ],
            'null_value' => null,
            'boolean' => true,
            'number' => 42.5,
        ];

        MatchCacheManager::cache($component, $matchId, $complexData);

        $result = MatchCacheManager::get($component, $matchId);
        $this->assertEquals($complexData, $result);
    }
}
