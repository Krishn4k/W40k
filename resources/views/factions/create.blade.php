@extends('layouts.app')

@section('title', 'Nouvelle faction')

@section('content')
    <h1 class="mb-6 text-2xl font-semibold">Nouvelle faction</h1>

    <form method="POST" action="{{ route('factions.store') }}" class="card flex max-w-3xl flex-col gap-4">
        @include('factions._form')

        <div class="flex gap-2">
            <button type="submit" class="btn-primary">Créer</button>
            <a href="{{ route('factions.index') }}" class="btn-secondary">Annuler</a>
        </div>
    </form>
@endsection
