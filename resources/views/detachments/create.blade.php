@extends('layouts.app')

@section('title', 'Nouveau détachement')

@section('content')
    <p class="text-sm"><a href="{{ route('factions.show', $faction) }}" class="link">{{ $faction->name }}</a></p>
    <h1 class="mb-6 text-2xl font-semibold">Nouveau détachement</h1>

    <form method="POST" action="{{ route('factions.detachments.store', $faction) }}" class="card flex max-w-4xl flex-col gap-6">
        @include('detachments._form')

        <div class="flex gap-2">
            <button type="submit" class="btn-primary">Créer</button>
            <a href="{{ route('factions.show', $faction) }}" class="btn-secondary">Annuler</a>
        </div>
    </form>
@endsection
