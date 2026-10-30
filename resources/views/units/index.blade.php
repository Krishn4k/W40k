@extends('layouts.app')

@section('title', 'Unités')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <p class="text-sm"><a href="{{ route('factions.index') }}" class="link">Factions</a></p>
            <h1 class="text-2xl font-semibold">Unités</h1>
        </div>
        @can('edit-reference')
        <a href="{{ route('units.create', ['faction_id' => $selectedFactionId]) }}" class="btn-primary">Nouvelle unité</a>
        @endcan
    </div>

    <form method="GET" action="{{ route('units.index') }}" x-data="autoSubmitFilters" data-results="#unit-results" class="mb-6 flex flex-wrap items-end gap-4">
        <div>
            <label for="faction" class="form-label">Faction</label>
            <select id="faction" name="faction" class="form-input">
                <option value="">Toutes</option>
                @foreach ($factionOptions as $id => $label)
                    <option value="{{ $id }}" @selected($selectedFactionId === $id)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="role" class="form-label">Rôle</label>
            <select id="role" name="role" class="form-input">
                <option value="">Tous</option>
                @foreach (App\Enums\UnitRole::cases() as $role)
                    <option value="{{ $role->value }}" @selected($selectedRole === $role)>{{ $role->label() }}</option>
                @endforeach
            </select>
        </div>
        @include('units._filters')
        <noscript><button type="submit" class="btn-secondary">Filtrer</button></noscript>
    </form>

    <div id="unit-results" class="aria-busy:opacity-60">
        <p class="mb-2 text-sm text-zinc-600 dark:text-zinc-400">{{ $units->total() }} unité(s)</p>
        @if ($units->isEmpty())
            <p class="card text-zinc-600 dark:text-zinc-400">Aucune unité ne correspond.</p>
        @else
            @include('units._table', ['units' => $units])
            <div class="mt-4">{{ $units->links() }}</div>
        @endif
    </div>
@endsection
