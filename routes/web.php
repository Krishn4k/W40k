<?php

use App\Http\Controllers\Admin\InvitationController;
use App\Http\Controllers\Admin\MaintenanceController;
use App\Http\Controllers\Admin\TranslationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ArmyListController;
use App\Http\Controllers\ArmyListEntryController;
use App\Http\Controllers\Auth\InvitationAcceptanceController;
use App\Http\Controllers\Auth\PasswordResetController;
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

    Route::get('invitation/{token}', [InvitationAcceptanceController::class, 'show'])->name('invitations.show');
    Route::post('invitation/{token}', [InvitationAcceptanceController::class, 'store'])->middleware('throttle:10,1')->name('invitations.accept');

    Route::get('reinitialiser-mot-de-passe/{token}', [PasswordResetController::class, 'show'])->name('password.reset');
    Route::post('reinitialiser-mot-de-passe', [PasswordResetController::class, 'store'])->middleware('throttle:10,1')->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::get('/', DashboardController::class)->name('home');
    Route::get('parametres', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('parametres', [SettingsController::class, 'update'])->name('settings.update');
    Route::put('parametres/mot-de-passe', [SettingsController::class, 'updatePassword'])->name('settings.password');
    Route::post('deconnexion', [SessionController::class, 'destroy'])->name('logout');

    Route::prefix('admin')->name('admin.')->middleware('can:admin')->group(function () {
        Route::get('utilisateurs', [UserController::class, 'index'])->name('users.index');
        Route::put('utilisateurs/{user}/admin', [UserController::class, 'toggleAdmin'])->name('users.toggle-admin');
        Route::post('utilisateurs/{user}/reinitialisation', [UserController::class, 'resetLink'])->name('users.reset-link');
        Route::post('invitations', [InvitationController::class, 'store'])->name('invitations.store');
        Route::delete('invitations/{invitation}', [InvitationController::class, 'destroy'])->name('invitations.destroy');
        Route::get('maintenance', [MaintenanceController::class, 'index'])->name('maintenance.index');
        Route::post('maintenance/migrations', [MaintenanceController::class, 'migrate'])->name('maintenance.migrate');
        Route::post('maintenance/optimisation', [MaintenanceController::class, 'optimize'])->name('maintenance.optimize');
        Route::post('maintenance/caches', [MaintenanceController::class, 'clearCaches'])->name('maintenance.clear-caches');
    });

    Route::resource('collection', CollectionLotController::class)
        ->except('show')
        ->parameters(['collection' => 'lot']);
    Route::patch('collection/{lot}/statuts', [CollectionLotController::class, 'updateStatuses'])->name('collection.statuses.update');
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

    Route::prefix('traductions')->name('translations.')->middleware('can:admin')->group(function () {
        Route::get('/', [TranslationController::class, 'overview'])->name('overview');
        Route::get('liste', [TranslationController::class, 'index'])->name('index');
        Route::put('{type}/{id}', [TranslationController::class, 'update'])->whereNumber('id')->name('update');
        Route::post('{type}/valider', [TranslationController::class, 'validateSelection'])->name('validate');
    });

    Route::resource('parties', GameController::class)
        ->names('games')
        ->parameters(['parties' => 'game']);

    // Shared reference data: everyone reads it, administrators change it.
    // Write routes come first so that ".../creer" is not taken for a record identifier.
    Route::prefix('referentiel')->middleware('can:admin')->group(function () {
        Route::resource('factions', FactionController::class)->except(['index', 'show']);
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
            ->except(['index', 'show'])
            ->names('point-sets')
            ->parameters(['versions-points' => 'point_set']);
        Route::resource('unites', UnitController::class)
            ->except(['index', 'show'])
            ->names('units')
            ->parameters(['unites' => 'unit']);
    });

    Route::prefix('referentiel')->group(function () {
        Route::resource('factions', FactionController::class)->only(['index', 'show']);
        Route::get('versions-points', [PointSetController::class, 'index'])->name('point-sets.index');
        Route::get('unites', [UnitController::class, 'index'])->name('units.index');
    });
});
