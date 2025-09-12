<?php

use Illuminate\Support\Facades\Route;
use App\Services\MatchCacheManager;

Route::get('/', function () {
    return view('app');
});

// Catch-all route for React Router (SPA) - exclude API routes
Route::get('/{any}', function () {
    return view('app');
})->where('any', '^(?!api).*');


Route::get('/test-cache/{matchId}', function ($matchId) {
    $components = ['match-details', 'player-stats', 'utility-analysis', 'grenade-explorer', 'head-to-head'];
    $cacheStatus = [];

    foreach ($components as $component) {
        $cacheStatus[$component] = MatchCacheManager::has($component, (int)$matchId);
    }

    return response()->json([
        'match_id' => $matchId,
        'cache_enabled' => config('app.cache_enabled', true),
        'cache_status' => $cacheStatus
    ]);
});
