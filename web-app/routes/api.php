<?php

use App\Http\Controllers\Api\DemoParserController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\UploadController;
use Illuminate\Http\Request;
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

Route::middleware('sanctum.auth')->get('/user', function (Request $request) {
    return $request->user();
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
