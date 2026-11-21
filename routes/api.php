<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BalanceAnalyticsController;
use App\Http\Controllers\BalancesController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UtilsController;
use App\Http\Controllers\V2;

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
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth-login');

    // Password Reset Routes
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:auth-reset');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:auth-reset');

    Route::group(['middleware' => 'auth:sanctum'], function () {
        Route::get('logout', [AuthController::class, 'logout']); // v1: revokes every token of the user
        Route::post('logout', [AuthController::class, 'logoutCurrent']);
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
        Route::get('load_balance/{id}', [BalancesController::class, 'load_balance']);
        Route::get('getValles/{user_id}', [UtilsController::class, 'getValles']);
        Route::get('getProcesos/{valle_id}', [UtilsController::class, 'getProcesos']);
        Route::get('getExcel/{datos_entrada_id}/{proceso_id}', [UtilsController::class, 'getExcel']);
        Route::get('corridas/{datos_entrada_id}', [BalancesController::class, 'corridas']);
    });

    Route::middleware('balance:write')->group(function () {
        Route::post('import', [BalancesController::class, 'import']);
        Route::post('correr_balance', [BalancesController::class, 'correr_balance']);
        Route::post('paint_tables', [BalancesController::class, 'paint_tables']);
        Route::post('clone_session', [BalancesController::class, 'clone_session']);
        Route::post('save_balance', [BalancesController::class, 'save_balance']);
        Route::post('corridas/{corrida_id}/restaurar', [BalancesController::class, 'restaurar_corrida']);
    });
});

Route::group(['prefix' => 'analytics', 'middleware' => ['auth:sanctum', 'dashboard']], function () {
    Route::get('dashboard', [BalanceAnalyticsController::class, 'dashboard']);
    Route::get('summary', [BalanceAnalyticsController::class, 'summary']);
    Route::get('by-user', [BalanceAnalyticsController::class, 'byUser']);
    Route::get('by-valle', [BalanceAnalyticsController::class, 'byValle']);
    Route::get('by-proceso', [BalanceAnalyticsController::class, 'byProceso']);
    Route::get('timeseries', [BalanceAnalyticsController::class, 'timeseries']);
    Route::get('filter-options', [BalanceAnalyticsController::class, 'filterOptions']);
});

// API v2 (Balances CMP web/): plain numeric payloads, readable errors. v1 routes above stay as they are.
Route::group(['prefix' => 'v2', 'middleware' => ['auth:sanctum']], function () {
    Route::middleware('balance:read')->group(function () {
        Route::get('inicio', [V2\InicioController::class, 'show']);
        Route::get('catalogo', [V2\BalancesController::class, 'catalogo']);
        Route::get('balances', [V2\BalancesController::class, 'listado']);
        Route::get('balances/{balance}/sesion', [V2\BalancesController::class, 'sesion'])->whereNumber('balance');
        Route::get('sesiones/{sesion}', [V2\SesionesController::class, 'show'])->whereNumber('sesion');
        Route::get('sesiones/{sesion}/corridas', [V2\SesionesController::class, 'corridas'])->whereNumber('sesion');
        Route::get('sesiones/{sesion}/diagnostico', [V2\DiagnosticoController::class, 'show'])->whereNumber('sesion');
        Route::get('procesos/{proceso}/diagrama', [V2\DiagramasController::class, 'show'])->whereNumber('proceso');
    });

    Route::middleware('balance:write')->group(function () {
        Route::post('sesiones', [V2\SesionesController::class, 'importar']);
        Route::put('sesiones/{sesion}/datos', [V2\SesionesController::class, 'guardarDatos'])->whereNumber('sesion');
        Route::post('sesiones/{sesion}/correr', [V2\SesionesController::class, 'correr'])->whereNumber('sesion');
        Route::post('sesiones/{sesion}/clonar', [V2\SesionesController::class, 'clonar'])->whereNumber('sesion');
        Route::post('sesiones/{sesion}/corridas/{corrida}/restaurar', [V2\SesionesController::class, 'restaurar'])->whereNumber(['sesion', 'corrida']);
        Route::post('sesiones/{sesion}/guardar', [V2\SesionesController::class, 'guardar'])->whereNumber('sesion');
        Route::post('sesiones/{sesion}/asistente', [V2\AsistenteController::class, 'responder'])->whereNumber('sesion')->middleware('throttle:asistente');
        Route::get('diagramas', [V2\DiagramasController::class, 'index']);
        Route::put('procesos/{proceso}/diagrama', [V2\DiagramasController::class, 'update'])->whereNumber('proceso');
    });
});
