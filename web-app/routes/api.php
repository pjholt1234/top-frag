<?php

use App\Http\Controllers\Api\AimController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DemoParserController;
use App\Http\Controllers\Api\GrenadeFavouriteController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MapStatsController;
use App\Http\Controllers\Api\MatchController;
use App\Http\Controllers\Api\RanksController;
use App\Http\Controllers\Api\SteamSharecodeController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\UtilityController;
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

// Steam authentication routes
Route::get('/auth/steam/redirect', [AuthController::class, 'steamRedirect']);
Route::get('/auth/steam/callback', [AuthController::class, 'steamCallback']);
Route::get('/auth/steam/link-callback', [AuthController::class, 'steamLinkCallback']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/user', [AuthController::class, 'user']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/steam/link', [AuthController::class, 'linkSteam']);
    Route::post('/auth/steam/unlink', [AuthController::class, 'unlinkSteam']);
    Route::post('/auth/change-password', [AuthController::class, 'changePassword']);
    Route::post('/auth/change-username', [AuthController::class, 'changeUsername']);
    Route::post('/auth/change-email', [AuthController::class, 'changeEmail']);

    // Steam sharecode routes
    Route::post('/steam-sharecode', [SteamSharecodeController::class, 'store']);
    Route::get('/steam-sharecode/has-sharecode', [SteamSharecodeController::class, 'hasSharecode']);
    Route::delete('/steam-sharecode', [SteamSharecodeController::class, 'destroy']);
    Route::post('/steam-sharecode/toggle-processing', [SteamSharecodeController::class, 'toggleProcessing']);

    Route::get('/matches', [MatchController::class, 'index']);

    // Dashboard routes
    Route::get('/dashboard/player-stats', [DashboardController::class, 'playerStats']);
    Route::get('/dashboard/aim-stats', [DashboardController::class, 'aimStats']);
    Route::get('/dashboard/utility-stats', [DashboardController::class, 'utilityStats']);
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
    Route::get('/dashboard/map-stats', [DashboardController::class, 'mapStats']);
    Route::get('/dashboard/rank-stats', [DashboardController::class, 'rankStats']);

    // Player card route
    Route::get('/player-card/{steamId}', [\App\Http\Controllers\Api\PlayerCardController::class, 'getPlayerCard']);

    // Dedicated page routes
    Route::get('/aim', [AimController::class, 'index']);
    Route::get('/aim/weapons', [AimController::class, 'weapons']);
    Route::get('/aim/hit-distribution', [AimController::class, 'hitDistribution']);
    Route::get('/utility', [UtilityController::class, 'index']);
    Route::get('/map-stats', [MapStatsController::class, 'index']);
    Route::get('/ranks', [RanksController::class, 'index']);

    // Match detail sections
    Route::get('/matches/{matchId}/match-details', [MatchController::class, 'matchDetails']);
    Route::get('/matches/{matchId}/player-stats', [MatchController::class, 'playerStats']);
    Route::get('/matches/{matchId}/top-role-players', [MatchController::class, 'topRolePlayers']);
    Route::get('/matches/{matchId}/utility-analysis', [MatchController::class, 'utilityAnalysis']);
    Route::get('/matches/{matchId}/grenade-explorer', [MatchController::class, 'grenadeExplorer']);
    Route::get('/matches/{matchId}/grenade-explorer/filter-options', [MatchController::class, 'grenadeExplorerFilterOptions']);
    Route::get('/matches/{matchId}/head-to-head', [MatchController::class, 'headToHead']);
    Route::get('/matches/{matchId}/head-to-head/player', [MatchController::class, 'headToHeadPlayer']);
    Route::get('/matches/{matchId}/aim-tracking', [MatchController::class, 'aimTracking']);
    Route::get('/matches/{matchId}/aim-tracking/weapon', [MatchController::class, 'aimTrackingWeapon']);
    Route::get('/matches/{matchId}/aim-tracking/filter-options', [MatchController::class, 'aimTrackingFilterOptions']);
    Route::post('/user/upload/demo', [UploadController::class, 'userDemo']);
    Route::get('/user/upload/in-progress-jobs', [UploadController::class, 'getInProgressJobs']);

    // Grenade Favourites routes
    Route::get('/grenade-favourites', [GrenadeFavouriteController::class, 'index']);
    Route::get('/grenade-favourites/filter-options', [GrenadeFavouriteController::class, 'filterOptions']);
    Route::post('/grenade-favourites', [GrenadeFavouriteController::class, 'create']);
    Route::get('/grenade-favourites/check', [GrenadeFavouriteController::class, 'check']);
    Route::get('/matches/{matchId}/grenade-favourites', [GrenadeFavouriteController::class, 'getMatchFavourites']);
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
