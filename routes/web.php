<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Livewire\Ballot;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);

    Route::get('/passwort-vergessen', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/passwort-vergessen', [PasswordResetController::class, 'email'])
        ->middleware('throttle:6,1')
        ->name('password.email');
    Route::get('/passwort-setzen/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/passwort-setzen', [PasswordResetController::class, 'update'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::livewire('/', Ballot::class)->name('home');
});
