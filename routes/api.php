<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BalancesController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UtilsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(['prefix' => 'auth'], function () {
    Route::post('login', [AuthController::class, 'login']);

    // Password Reset Routes
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);

    Route::group(['middleware' => 'auth:sanctum'], function () {
        Route::get('logout', [AuthController::class, 'logout']);
        Route::get('user', [AuthController::class, 'user']);
    });
});

Route::group(['middleware' => ['auth:sanctum', 'admin']], function () {
    Route::apiResource('users', UserController::class)->except(['show']);
});

// Balances Routes
Route::group(['prefix' => 'balances', 'middleware' => ['auth:sanctum']], function () {
    Route::middleware('balance:read')->group(function () {
        Route::get('get_listado', [BalancesController::class, 'get_listado']);
        Route::get('getValles/{user_id}', [UtilsController::class, 'getValles']);
        Route::get('getProcesos/{valle_id}', [UtilsController::class, 'getProcesos']);
        Route::get('getExcel/{datos_entrada_id}/{proceso_id}', [UtilsController::class, 'getExcel']);
    });

    Route::middleware('balance:write')->group(function () {
        Route::post('import', [BalancesController::class, 'import']);
        Route::post('correr_balance', [BalancesController::class, 'correr_balance']);
        Route::post('paint_tables', [BalancesController::class, 'paint_tables']);
        Route::post('save_balance', [BalancesController::class, 'save_balance']);
    });
});
