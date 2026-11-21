<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApplicationController;

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

// Password Reset Routes (named: the reset email builds its link from them). Served by the v2 SPA (web/).
Route::view('reset-password/{token}', 'app')->name('password.reset');
Route::view('forgot-password', 'app')->name('password.request');

// The Vue 3 app used to live under /v3: keep old links working.
Route::get('/v3/{any?}', function (\Illuminate\Http\Request $request, $any = '') {
    $query = $request->getQueryString();

    return redirect('/' . ltrim($any, '/') . ($query ? '?' . $query : ''), 301); // ltrim: no //evil.com
})->where('any', '.*');

Route::get('/{any}', [ApplicationController::class, 'index'])->where('any', '.*');

