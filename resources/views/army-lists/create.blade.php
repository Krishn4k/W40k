@extends('layouts.app')

@section('title', 'Nouvelle liste')

@section('content')
    <p class="text-sm"><a href="{{ route('army-lists.index') }}" class="link">Listes d'armée</a></p>
    <h1 class="mb-6 text-2xl font-semibold">Nouvelle liste</h1>

    <form method="POST" action="{{ route('army-lists.store') }}" class="card flex max-w-3xl flex-col gap-4">
        @include('army-lists._form')

        <div class="flex gap-2">
            <button type="submit" class="btn-primary">Créer</button>
            <a href="{{ route('army-lists.index') }}" class="btn-secondary">Annuler</a>
        </div>
    </form>
@endsection
