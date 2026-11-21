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

// Old Vue 2 paths (www bookmarks) → their v2 equivalents.
Route::permanentRedirect('balances/Listado', '/balances');
Route::permanentRedirect('balances/Balances', '/balances');
Route::permanentRedirect('dashboard/balances', '/inicio');
Route::permanentRedirect('dashboard/balance-analytics', '/analytics');
Route::permanentRedirect('admin/users', '/admin/usuarios');
// Not permanent: where a balance opens depends on the data. Exposes only balance id → session id; the SPA still asks for login.
Route::get('balances/BalanceFormWizard', function (\Illuminate\Http\Request $request) {
    if (!$request->filled('balance_id')) return redirect('/balances/nuevo');
    $sesion = ctype_digit((string) $request->query('balance_id'))
        ? \App\Models\Balances::query()->find((int) $request->query('balance_id'))?->datosEntrada?->id
        : null;

    return redirect($sesion ? "/balances/sesion/{$sesion}" : '/balances');
});

Route::get('/{any}', [ApplicationController::class, 'index'])->where('any', '.*');

