@extends('layouts.app')

@section('title', 'Maintenance')

@section('content')
    @include('admin._nav')

    <div class="mb-6">
        <h1 class="text-2xl font-semibold">Maintenance</h1>
        <p class="text-sm text-zinc-600 dark:text-zinc-400">Commandes de mise à jour, pour un hébergement sans accès SSH. Chaque action est inscrite dans <code>storage/logs/maintenance.log</code>.</p>
    </div>

    @if (session('maintenance_output'))
        <section class="card mb-6">
            <h2 class="mb-2 text-lg font-semibold">Compte rendu</h2>
            <pre class="overflow-x-auto rounded-md bg-zinc-100 p-3 text-xs whitespace-pre-wrap dark:bg-zinc-950">{{ session('maintenance_output') }}</pre>
        </section>
    @endif

    <section class="card mb-6 flex flex-wrap items-center justify-between gap-4">
        <div class="max-w-3xl text-sm">
            <h2 class="mb-1 text-lg font-semibold">Référentiel (mise à jour des points)</h2>
            <p class="text-zinc-600 dark:text-zinc-400">
                Sur un poste de travail : télécharger ce fichier, le charger avec <code>php artisan reference:import-sql fichier.sql.gz</code>,
                lancer <code>php artisan bsdata:import</code>, puis importer dans phpMyAdmin le fichier produit par <code>php artisan reference:export-sql</code>.
            </p>
            <p class="mt-1 text-amber-700 dark:text-amber-400">Pendant ce temps, ne pas modifier le référentiel en ligne (unités, détachements, traductions) : ces changements seraient écrasés.</p>
        </div>
        <a href="{{ route('admin.maintenance.reference') }}" class="btn-secondary">Télécharger le référentiel</a>
    </section>

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="card">
            <h2 class="mb-3 text-lg font-semibold">État</h2>
            <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-sm">
                <dt class="text-zinc-500">Version</dt>
                <dd class="font-mono">{{ $version }}</dd>
                <dt class="text-zinc-500">Environnement</dt>
                <dd>{{ app()->environment() }}{{ config('app.debug') ? ' (debug)' : '' }}</dd>
                <dt class="text-zinc-500">PHP</dt>
                <dd>{{ PHP_VERSION }}</dd>
                <dt class="text-zinc-500">Laravel</dt>
                <dd>{{ app()->version() }}</dd>
                <dt class="text-zinc-500">Configuration</dt>
                <dd>{{ $configCached ? 'en cache' : 'non mise en cache' }}</dd>
                <dt class="text-zinc-500">Routes</dt>
                <dd>{{ $routesCached ? 'en cache' : 'non mises en cache' }}</dd>
            </dl>
        </section>

        <section class="card flex flex-col gap-3">
            <h2 class="text-lg font-semibold">Base de données</h2>
            @if ($pendingMigrations === [])
                <p class="text-sm text-zinc-600 dark:text-zinc-400">La base est à jour : aucune migration en attente.</p>
            @else
                <p class="text-sm">{{ count($pendingMigrations) }} migration(s) en attente :</p>
                <ul class="list-inside list-disc text-xs font-mono text-zinc-600 dark:text-zinc-400">
                    @foreach ($pendingMigrations as $migration)
                        <li>{{ $migration }}</li>
                    @endforeach
                </ul>
                <p class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                    Avant d'appliquer : exporter la base dans phpMyAdmin (sauvegarde).
                </p>
            @endif
            <form method="POST" action="{{ route('admin.maintenance.migrate') }}" onsubmit="return confirm('Appliquer les migrations en attente ? Pense à sauvegarder la base avant.')" class="mt-auto">
                @csrf
                <button type="submit" class="btn-primary w-full" @disabled($pendingMigrations === [])>Appliquer les migrations</button>
            </form>
        </section>

        <section class="card flex flex-col gap-3">
            <h2 class="text-lg font-semibold">Performances</h2>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                Après chaque mise en ligne : <strong>Optimiser</strong> met en cache la configuration, les événements et les vues.
                Après une modification du <code>.env</code> ou un comportement étrange : <strong>Vider les caches</strong>.
            </p>
            <div class="mt-auto flex flex-col gap-2">
                <form method="POST" action="{{ route('admin.maintenance.optimize') }}" onsubmit="return confirm('Optimiser l\'application (mise en cache) ?')">
                    @csrf
                    <button type="submit" class="btn-primary w-full">Optimiser</button>
                </form>
                <form method="POST" action="{{ route('admin.maintenance.clear-caches') }}" onsubmit="return confirm('Vider tous les caches de l\'application ?')">
                    @csrf
                    <button type="submit" class="btn-secondary w-full">Vider les caches</button>
                </form>
            </div>
        </section>
    </div>
@endsection
