<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DemoParserController;
use App\Http\Controllers\Api\GrenadeFavouriteController;
use App\Http\Controllers\Api\GrenadeLibraryController;
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

// Authentication routes
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/user', [AuthController::class, 'user']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/matches', [MatchController::class, 'index']);
    Route::get('/matches/{matchId}', [MatchController::class, 'show']);
    Route::post('/user/upload/demo', [UploadController::class, 'userDemo']);

    // Grenade Library routes
    Route::get('/grenade-library', [GrenadeLibraryController::class, 'index']);
    Route::get('/grenade-library/filter-options', [GrenadeLibraryController::class, 'filterOptions']);

    // Grenade Favourites routes
    Route::get('/grenade-favourites', [GrenadeFavouriteController::class, 'index']);
    Route::post('/grenade-favourites', [GrenadeFavouriteController::class, 'create']);
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
