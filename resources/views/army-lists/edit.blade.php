@extends('layouts.app')

@section('title', 'Modifier '.$armyList->name)

@section('content')
    <p class="text-sm"><a href="{{ route('army-lists.show', $armyList) }}" class="link">{{ $armyList->name }}</a></p>
    <h1 class="mb-6 text-2xl font-semibold">Modifier la liste</h1>

    <form method="POST" action="{{ route('army-lists.update', $armyList) }}" class="card flex max-w-3xl flex-col gap-4">
        @method('PUT')
        @include('army-lists._form')

        <div class="flex gap-2">
            <button type="submit" class="btn-primary">Enregistrer</button>
            <a href="{{ route('army-lists.show', $armyList) }}" class="btn-secondary">Annuler</a>
        </div>
    </form>
@endsection
