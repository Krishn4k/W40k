@extends('layouts.app')

@section('title', 'Modifier '.$detachment->name)

@section('content')
    @include('partials.errors', ['keys' => ['detachment']])

    <p class="text-sm"><a href="{{ route('factions.show', $faction) }}" class="link">{{ $faction->name }}</a></p>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <h1 class="text-2xl font-semibold">Modifier {{ $detachment->name }}</h1>
        <form method="POST" action="{{ route('detachments.destroy', $detachment) }}" onsubmit="return confirm('Supprimer ce détachement et ses optimisations ?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn-danger">Supprimer</button>
        </form>
    </div>

    <form method="POST" action="{{ route('detachments.update', $detachment) }}" class="card flex max-w-4xl flex-col gap-6">
        @method('PUT')
        @include('detachments._form')

        <div class="flex gap-2">
            <button type="submit" class="btn-primary">Enregistrer</button>
            <a href="{{ route('factions.show', $faction) }}" class="btn-secondary">Annuler</a>
        </div>
    </form>
@endsection
