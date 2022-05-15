<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\BalancesController;
use App\Http\Controllers\UtilsController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/


Route::post('balances/import', [BalancesController::class, 'import']);
Route::get('balances/get_listado', [BalancesController::class, 'get_listado']);
Route::post('balances/correr_balance', [BalancesController::class, 'correr_balance']);
Route::post('balances/paint_tables', [BalancesController::class, 'paint_tables']);
Route::post('balances/save_balance', [BalancesController::class, 'save_balance']);
Route::get('balances/getValles/{user_id}', [UtilsController::class, 'getValles']);
Route::get('balances/getProcesos/{valle_id}', [UtilsController::class, 'getProcesos']);
Route::get('/{any}', [ApplicationController::class, 'index'])->where('any', '.*');
