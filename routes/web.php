<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\BalancesController;

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

Route::get('/{any}', [ApplicationController::class, 'index'])->where('any', '.*');
Route::post('balances/import', [BalancesController::class, 'import']);
Route::post('balances/correr_balance', [BalancesController::class, 'correr_balance']);
