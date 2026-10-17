@extends('layouts.app')

@section('title', 'Factions')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">Factions</h1>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">Référentiel partagé : factions, sous-factions et leurs unités.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('point-sets.index') }}" class="btn-secondary">Versions des points</a>
            <a href="{{ route('units.index') }}" class="btn-secondary">Toutes les unités</a>
            <a href="{{ route('factions.create') }}" class="btn-primary">Nouvelle faction</a>
        </div>
    </div>

    @if ($factions->isEmpty())
        <p class="card text-zinc-600 dark:text-zinc-400">Aucune faction pour l'instant.</p>
    @else
        <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($factions as $faction)
                <li class="card">
                    <a href="{{ route('factions.show', $faction) }}" class="link text-lg font-semibold">{{ $faction->name }}</a>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $faction->units_count }} unité(s)</p>
                    @if ($faction->subFactions->isNotEmpty())
                        <ul class="mt-3 flex flex-col gap-1 border-t border-zinc-200 pt-3 text-sm dark:border-zinc-800">
                            @foreach ($faction->subFactions->sortBy(fn ($subFaction) => (string) $subFaction->name) as $subFaction)
                                <li>
                                    ↳ <a href="{{ route('factions.show', $subFaction) }}" class="link">{{ $subFaction->name }}</a>
                                    <span class="text-zinc-500">({{ $subFaction->units_count }} unité(s) propres)</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
@endsection
