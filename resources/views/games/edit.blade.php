@extends('layouts.app')

@section('title', 'Modifier la partie')

@section('content')
    <p class="text-sm"><a href="{{ route('games.show', $game) }}" class="link">Partie du {{ $game->played_on->format('d/m/Y') }}</a></p>
    <h1 class="mb-6 text-2xl font-semibold">Modifier la partie</h1>

    <form method="POST" action="{{ route('games.update', $game) }}" class="card flex max-w-4xl flex-col gap-6">
        @method('PUT')
        @include('games._form')

        <div class="flex gap-2">
            <button type="submit" class="btn-primary">Enregistrer</button>
            <a href="{{ route('games.show', $game) }}" class="btn-secondary">Annuler</a>
        </div>
    </form>
@endsection
