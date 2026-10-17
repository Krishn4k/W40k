<?php

namespace App\Providers;

use App\Models\Detachment;
use App\Models\Faction;
use App\Models\GameFormat;
use Illuminate\Database\Eloquent\Relations\Relation;
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
        Relation::enforceMorphMap([
            'faction' => Faction::class,
            'detachment' => Detachment::class,
            'game_format' => GameFormat::class,
        ]);
    }
}
