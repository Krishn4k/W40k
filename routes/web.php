<?php

use App\Http\Controllers\ArmyListController;
use App\Http\Controllers\ArmyListEntryController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\CollectionLotController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DetachmentController;
use App\Http\Controllers\FactionController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\LotPhotoController;
use App\Http\Controllers\PointSetController;
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

    Route::resource('listes', ArmyListController::class)
        ->names('army-lists')
        ->parameters(['listes' => 'army_list']);
    Route::post('listes/{army_list}/dupliquer', [ArmyListController::class, 'duplicate'])->name('army-lists.duplicate');
    Route::get('listes/{army_list}/export', [ArmyListController::class, 'export'])->name('army-lists.export');
    Route::scopeBindings()->prefix('listes/{army_list}/unites')->name('army-lists.entries.')->group(function () {
        Route::post('/', [ArmyListEntryController::class, 'store'])->name('store');
        Route::put('{entry}', [ArmyListEntryController::class, 'update'])->name('update');
        Route::delete('{entry}', [ArmyListEntryController::class, 'destroy'])->name('destroy');
    });

    Route::resource('parties', GameController::class)
        ->names('games')
        ->parameters(['parties' => 'game']);

    Route::prefix('referentiel')->group(function () {
        Route::resource('factions', FactionController::class);
        Route::resource('factions.detachements', DetachmentController::class)
            ->shallow()
            ->only(['create', 'store', 'edit', 'update', 'destroy'])
            ->names([
                'create' => 'factions.detachments.create',
                'store' => 'factions.detachments.store',
                'edit' => 'detachments.edit',
                'update' => 'detachments.update',
                'destroy' => 'detachments.destroy',
            ])
            ->parameters(['detachements' => 'detachment']);
        Route::resource('versions-points', PointSetController::class)
            ->except('show')
            ->names('point-sets')
            ->parameters(['versions-points' => 'point_set']);
        Route::resource('unites', UnitController::class)
            ->except('show')
            ->names('units')
            ->parameters(['unites' => 'unit']);
    });
});
