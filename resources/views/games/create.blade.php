@extends('layouts.app')

@section('title', 'Nouvelle partie')

@section('content')
    <p class="text-sm"><a href="{{ route('games.index') }}" class="link">Parties</a></p>
    <h1 class="mb-6 text-2xl font-semibold">Nouvelle partie</h1>

    <form method="POST" action="{{ route('games.store') }}" class="card flex max-w-4xl flex-col gap-6">
        @include('games._form')

        <div class="flex gap-2">
            <button type="submit" class="btn-primary">Enregistrer</button>
            <a href="{{ route('games.index') }}" class="btn-secondary">Annuler</a>
        </div>
    </form>
@endsection
