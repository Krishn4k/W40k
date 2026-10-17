<?php

namespace App\Providers;

use App\Models\Detachment;
use App\Models\Faction;
use App\Models\GameFormat;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Route::resourceVerbs(['create' => 'creer', 'edit' => 'modifier']);

        Gate::define('admin', fn (User $user): bool => $user->is_admin);

        Relation::enforceMorphMap([
            'faction' => Faction::class,
            'detachment' => Detachment::class,
            'game_format' => GameFormat::class,
        ]);
    }
}
