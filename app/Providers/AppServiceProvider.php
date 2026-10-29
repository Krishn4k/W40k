<?php

namespace App\Providers;

use App\Models\Detachment;
use App\Models\Enhancement;
use App\Models\Faction;
use App\Models\GameFormat;
use App\Models\Keyword;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
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

        // Behind the host's proxy (OVH), the request may look like http: keep links and assets in https.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Gate::define('admin', fn (User $user): bool => $user->is_admin);

        Relation::enforceMorphMap([
            'faction' => Faction::class,
            'detachment' => Detachment::class,
            'game_format' => GameFormat::class,
            'unit' => Unit::class,
            'keyword' => Keyword::class,
            'enhancement' => Enhancement::class,
        ]);
    }
}
