@extends('layouts.app')

@section('title', 'Modifier '.$faction->name)

@section('content')
    <h1 class="mb-6 text-2xl font-semibold">Modifier {{ $faction->name }}</h1>

    <form method="POST" action="{{ route('factions.update', $faction) }}" class="card flex max-w-3xl flex-col gap-4">
        @method('PUT')
        @include('factions._form')

        <div class="flex gap-2">
            <button type="submit" class="btn-primary">Enregistrer</button>
            <a href="{{ route('factions.show', $faction) }}" class="btn-secondary">Annuler</a>
        </div>
    </form>
@endsection
