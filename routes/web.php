<?php

use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\CollectionLotController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FactionController;
use App\Http\Controllers\LotPhotoController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UnitController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('connexion', [SessionController::class, 'create'])->name('login');
    Route::post('connexion', [SessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/', DashboardController::class)->name('home');
    Route::get('parametres', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('parametres', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('deconnexion', [SessionController::class, 'destroy'])->name('logout');

    Route::resource('collection', CollectionLotController::class)
        ->except('show')
        ->parameters(['collection' => 'lot']);
    Route::scopeBindings()->prefix('collection/{lot}/photos')->name('collection.photos.')->group(function () {
        Route::post('/', [LotPhotoController::class, 'store'])->name('store');
        Route::get('{photo}', [LotPhotoController::class, 'show'])->name('show');
        Route::delete('{photo}', [LotPhotoController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('referentiel')->group(function () {
        Route::resource('factions', FactionController::class);
        Route::resource('unites', UnitController::class)
            ->except('show')
            ->names('units')
            ->parameters(['unites' => 'unit']);
    });
});
