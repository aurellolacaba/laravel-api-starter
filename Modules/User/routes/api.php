<?php

use Illuminate\Support\Facades\Route;
use Modules\User\Http\Controllers\UserController;

/*
|--------------------------------------------------------------------------
| User Module API Routes
|--------------------------------------------------------------------------
|
| These routes are automatically prefixed with "api" by the module's
| RouteServiceProvider, so the paths below resolve under "/api/users".
|
*/

Route::middleware('auth:api')->group(function () {
    Route::post('users', [UserController::class, 'store']);
    Route::get('users', [UserController::class, 'all']);
});
