<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MatchController;
use App\Http\Controllers\Api\PlayerController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\UploadController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Health check endpoint (public)
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'message' => 'Top Frag API is running',
        'timestamp' => now()->toISOString()
    ]);
});

// User endpoint (requires Sanctum authentication)
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// All other endpoints require API key authentication
Route::middleware('api.key')->group(function () {
    // Match endpoints
    Route::prefix('matches')->group(function () {
        Route::get('/', [MatchController::class, 'index']);
        Route::get('/{match}', [MatchController::class, 'show']);
        Route::post('/', [MatchController::class, 'store']);
        Route::put('/{match}', [MatchController::class, 'update']);
        Route::delete('/{match}', [MatchController::class, 'destroy']);
    });

    // Player endpoints
    Route::prefix('players')->group(function () {
        Route::get('/', [PlayerController::class, 'index']);
        Route::get('/{player}', [PlayerController::class, 'show']);
        Route::get('/{player}/matches', [PlayerController::class, 'matches']);
        Route::get('/{player}/stats', [PlayerController::class, 'stats']);
    });

    // Stats endpoints
    Route::prefix('stats')->group(function () {
        Route::get('/matches', [StatsController::class, 'matches']);
        Route::get('/players', [StatsController::class, 'players']);
        Route::get('/leaderboards', [StatsController::class, 'leaderboards']);
    });

    // Upload endpoints
    Route::prefix('upload')->group(function () {
        Route::post('/demo', [UploadController::class, 'demo']);
        Route::get('/status/{job}', [UploadController::class, 'status']);
    });
});
