@extends('layouts.app')

@section('title', 'Modifier '.$lot->unit->name)

@section('content')
    <p class="text-sm"><a href="{{ route('collection.index') }}" class="link">Collection</a></p>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <h1 class="text-2xl font-semibold">{{ $lot->unit->name }} @if ($lot->label)<span class="text-zinc-500">— {{ $lot->label }}</span>@endif</h1>
        <form method="POST" action="{{ route('collection.destroy', $lot) }}" onsubmit="return confirm('Supprimer ce lot et ses photos ?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn-danger">Supprimer le lot</button>
        </form>
    </div>

    <form method="POST" action="{{ route('collection.update', $lot) }}" class="card mb-8 flex max-w-4xl flex-col gap-6">
        @method('PUT')
        @include('collection._form')

        <div class="flex gap-2">
            <button type="submit" class="btn-primary">Enregistrer</button>
            <a href="{{ route('collection.index') }}" class="btn-secondary">Annuler</a>
        </div>
    </form>

    @includeWhen(Route::has('collection.photos.store'), 'collection._photos')
@endsection
