<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Api\MatchController;
use App\Http\Controllers\Api\PlayerController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\DemoParserController;

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

Route::get('/health', [HealthController::class, 'check']);

Route::middleware('sanctum.auth')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('api.key')->group(function () {
    Route::prefix('matches')->group(function () {
        Route::get('/', [MatchController::class, 'index']);
        Route::get('/{match}', [MatchController::class, 'show']);
        Route::post('/', [MatchController::class, 'store']);
        Route::put('/{match}', [MatchController::class, 'update']);
        Route::delete('/{match}', [MatchController::class, 'destroy']);
    });

    Route::prefix('players')->group(function () {
        Route::get('/', [PlayerController::class, 'index']);
        Route::get('/{player}', [PlayerController::class, 'show']);
        Route::get('/{player}/matches', [PlayerController::class, 'matches']);
        Route::get('/{player}/stats', [PlayerController::class, 'stats']);
    });

    Route::prefix('stats')->group(function () {
        Route::get('/matches', [StatsController::class, 'matches']);
        Route::get('/players', [StatsController::class, 'players']);
        Route::get('/leaderboards', [StatsController::class, 'leaderboards']);
    });

    Route::prefix('upload')->group(function () {
        Route::post('/demo', [UploadController::class, 'demo']);
        Route::get('/status/{job}', [UploadController::class, 'status']);
        Route::post('/callback/progress', [UploadController::class, 'progressCallback']);
        Route::post('/callback/completion', [UploadController::class, 'completionCallback']);
    });

    // Demo parser endpoints - new format
    Route::prefix('job')->group(function () {
        Route::post('/{jobId}/event/{eventName}', [DemoParserController::class, 'handleEvent']);
    });
});
