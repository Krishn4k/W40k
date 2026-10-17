@extends('layouts.app')

@section('title', 'Modifier '.$pointSet->name)

@section('content')
    <p class="text-sm"><a href="{{ route('point-sets.index') }}" class="link">Versions des points</a></p>
    <h1 class="mb-6 text-2xl font-semibold">Modifier {{ $pointSet->name }}</h1>

    <form method="POST" action="{{ route('point-sets.update', $pointSet) }}" class="card flex max-w-2xl flex-col gap-4">
        @method('PUT')
        @include('point-sets._form')

        <div class="flex gap-2">
            <button type="submit" class="btn-primary">Enregistrer</button>
            <a href="{{ route('point-sets.index') }}" class="btn-secondary">Annuler</a>
        </div>
    </form>
@endsection
