@extends('layouts.app')

@section('title', 'Modifier '.$unit->name)

@section('content')
    @include('partials.errors', ['keys' => ['unit']])

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <h1 class="text-2xl font-semibold">Modifier {{ $unit->name }}</h1>
        <form method="POST" action="{{ route('units.destroy', $unit) }}" onsubmit="return confirm('Supprimer cette unité ?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn-danger">Supprimer</button>
        </form>
    </div>

    <form method="POST" action="{{ route('units.update', $unit) }}" class="card flex max-w-4xl flex-col gap-6">
        @method('PUT')
        @include('units._form')

        <div class="flex gap-2">
            <button type="submit" class="btn-primary">Enregistrer</button>
            <a href="{{ route('factions.show', $unit->faction_id) }}" class="btn-secondary">Annuler</a>
        </div>
    </form>
@endsection
