<?php

use App\Http\Controllers\Admin\StandingsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EntryController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showEmail'])->name('login');
    Route::post('/login', [AuthController::class, 'sendCode'])->middleware('throttle:6,1');
    Route::get('/login/code', [AuthController::class, 'showCode'])->name('login.code');
    Route::post('/login/code', [AuthController::class, 'verifyCode'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', DashboardController::class)->name('home');
    Route::get('/today', [EntryController::class, 'edit'])->name('entry');
    Route::post('/today', [EntryController::class, 'update']);

    Route::middleware('program-role:wellness_admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/standings', StandingsController::class)->name('standings');
    });
});
