@extends('layouts.app')

@section('title', 'Ajouter un lot')

@section('content')
    <p class="text-sm"><a href="{{ route('collection.index') }}" class="link">Collection</a></p>
    <h1 class="mb-6 text-2xl font-semibold">Ajouter un lot</h1>

    <form method="POST" action="{{ route('collection.store') }}" class="card flex max-w-4xl flex-col gap-6">
        @include('collection._form')

        <div class="flex gap-2">
            <button type="submit" class="btn-primary">Ajouter</button>
            <a href="{{ route('collection.index') }}" class="btn-secondary">Annuler</a>
        </div>
    </form>
@endsection
