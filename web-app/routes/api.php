<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DemoParserController;
use App\Http\Controllers\Api\GrenadeFavouriteController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MatchController;
use App\Http\Controllers\Api\UploadController;
use Illuminate\Support\Facades\Route;

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

// Test utility analysis directly
Route::get('/test-utility/{matchId}', function ($matchId) {
    $user = \App\Models\User::first();
    if (! $user) {
        return response()->json(['error' => 'No users found']);
    }

    $service = new \App\Services\Matches\UtilityAnalysisService;
    $result = $service->getAnalysis($user, (int) $matchId);

    return response()->json([
        'match_id' => $matchId,
        'user_id' => $user->id,
        'result_keys' => array_keys($result),
    ]);
});

// Authentication routes
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/user', [AuthController::class, 'user']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/matches', [MatchController::class, 'index']);

    // Match detail sections
    Route::get('/matches/{matchId}/match-details', [MatchController::class, 'matchDetails']);
    Route::get('/matches/{matchId}/player-stats', [MatchController::class, 'playerStats']);
    Route::get('/matches/{matchId}/top-role-players', [MatchController::class, 'topRolePlayers']);
    Route::get('/matches/{matchId}/utility-analysis', [MatchController::class, 'utilityAnalysis']);
    Route::get('/matches/{matchId}/grenade-explorer', [MatchController::class, 'grenadeExplorer']);
    Route::get('/matches/{matchId}/grenade-explorer/filter-options', [MatchController::class, 'grenadeExplorerFilterOptions']);
    Route::get('/matches/{matchId}/head-to-head', [MatchController::class, 'headToHead']);
    Route::post('/user/upload/demo', [UploadController::class, 'userDemo']);

    // Grenade Favourites routes
    Route::get('/grenade-favourites', [GrenadeFavouriteController::class, 'index']);
    Route::get('/grenade-favourites/filter-options', [GrenadeFavouriteController::class, 'filterOptions']);
    Route::post('/grenade-favourites', [GrenadeFavouriteController::class, 'create']);
    Route::get('/grenade-favourites/check', [GrenadeFavouriteController::class, 'check']);
    Route::delete('/grenade-favourites/{id}', [GrenadeFavouriteController::class, 'delete']);
});

Route::middleware('api.key')->group(function () {
    Route::prefix('upload')
        ->controller(UploadController::class)
        ->group(function () {
            Route::post('/demo', 'demo');
        });

    Route::prefix('job')
        ->controller(DemoParserController::class)
        ->group(function () {
            Route::post('/{jobId}/event/{eventName}', 'handleEvent');
            Route::post('/callback/progress', 'progressCallback');
            Route::post('/callback/completion', 'completionCallback');
        });
});
