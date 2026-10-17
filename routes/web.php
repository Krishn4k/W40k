<?php

use App\Http\Controllers\Auth\SessionController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('connexion', [SessionController::class, 'create'])->name('login');
    Route::post('connexion', [SessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::view('/', 'home')->name('home');
    Route::post('deconnexion', [SessionController::class, 'destroy'])->name('logout');
});
