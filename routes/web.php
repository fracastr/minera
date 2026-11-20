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

// Password Reset Routes
Route::get('reset-password/{token}', function ($token) {
    return view('application');
})->name('password.reset');

Route::get('forgot-password', function () {
    return view('application');
})->name('password.request');

Route::get('/{any}', [ApplicationController::class, 'index'])->where('any', '.*');

