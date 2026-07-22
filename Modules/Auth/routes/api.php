<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\AuthController;

/*
|--------------------------------------------------------------------------
| Auth Module API Routes
|--------------------------------------------------------------------------
|
| These routes are automatically prefixed with "api" by the module's
| RouteServiceProvider, so the paths below resolve under "/api/auth/*".
|
*/

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('refresh', [AuthController::class, 'refresh'])->middleware('throttle:20,1');

    Route::middleware('auth:api')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});
