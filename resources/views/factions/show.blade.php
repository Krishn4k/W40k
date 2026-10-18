@extends('layouts.app')

@section('title', (string) $faction->name)

@section('content')
    @include('partials.errors', ['keys' => ['faction']])

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-sm">
                <a href="{{ route('factions.index') }}" class="link">Factions</a>
                @if ($faction->parent)
                    › <a href="{{ route('factions.show', $faction->parent) }}" class="link">{{ $faction->parent->name }}</a>
                @endif
            </p>
            <h1 class="text-2xl font-semibold">{{ $faction->name }}</h1>
            @if ($faction->name->get('en') !== (string) $faction->name)
                <p class="text-sm text-zinc-500">{{ $faction->name->get('en') }}</p>
            @endif
        </div>
        @can('admin')
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('units.create', ['faction_id' => $faction->id]) }}" class="btn-primary">Nouvelle unité</a>
            <a href="{{ route('factions.edit', $faction) }}" class="btn-secondary">Modifier</a>
            <form method="POST" action="{{ route('factions.destroy', $faction) }}" onsubmit="return confirm('Supprimer cette faction ?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-danger">Supprimer</button>
            </form>
        </div>
        @endcan
    </div>

    @if ($faction->subFactions->isNotEmpty())
        <p class="mb-6 text-sm">
            Sous-factions :
            @foreach ($faction->subFactions as $subFaction)
                <a href="{{ route('factions.show', $subFaction) }}" class="link">{{ $subFaction->name }}</a>{{ $loop->last ? '' : ',' }}
            @endforeach
        </p>
    @endif

    <section class="mb-8">
        <h2 class="mb-3 text-lg font-semibold">Unités disponibles ({{ $units->count() }})</h2>
        <form method="GET" action="{{ route('factions.show', $faction) }}" class="mb-4 flex flex-wrap items-end gap-4">
            @include('units._filters')
            <button type="submit" class="btn-secondary">Filtrer</button>
        </form>
        @if ($units->isEmpty())
            <p class="card text-zinc-600 dark:text-zinc-400">Aucune unité ne correspond.</p>
        @else
            @include('units._table', ['units' => $units, 'currentFaction' => $faction])
        @endif
    </section>

    <section>
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-lg font-semibold">Détachements</h2>
            @can('admin')
                <a href="{{ route('factions.detachments.create', $faction) }}" class="btn-secondary">Nouveau détachement</a>
            @endcan
        </div>
        @if ($faction->detachments->isEmpty())
            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                Aucun détachement propre à cette faction.
                @if ($faction->parent)
                    Ceux de {{ $faction->parent->name }} restent disponibles.
                @endif
            </p>
        @else
            <ul class="grid gap-4 sm:grid-cols-2">
                @foreach ($faction->detachments as $detachment)
                    <li class="card">
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="font-semibold">{{ $detachment->name }}</h3>
                            @can('admin')
                                <a href="{{ route('detachments.edit', $detachment) }}" class="link text-sm">Modifier</a>
                            @endcan
                        </div>
                        <ul class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                            @foreach ($detachment->enhancements as $enhancement)
                                <li>{{ $enhancement->name }}</li>
                            @endforeach
                        </ul>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
