@extends('layouts.app')

@section('title', 'Nouvelle unité')

@section('content')
    <h1 class="mb-6 text-2xl font-semibold">Nouvelle unité</h1>

    <form method="POST" action="{{ route('units.store') }}" class="card flex max-w-4xl flex-col gap-6">
        @include('units._form')

        <div class="flex gap-2">
            <button type="submit" class="btn-primary">Créer</button>
            <a href="{{ url()->previous() }}" class="btn-secondary">Annuler</a>
        </div>
    </form>
@endsection
