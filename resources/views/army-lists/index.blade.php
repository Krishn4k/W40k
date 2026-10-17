@extends('layouts.app')

@section('title', "Listes d'armée")

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">Listes d'armée</h1>
        <a href="{{ route('army-lists.create') }}" class="btn-primary">Nouvelle liste</a>
    </div>

    @if ($analyses->isEmpty())
        <p class="card text-zinc-600 dark:text-zinc-400">
            Aucune liste pour l'instant. <a href="{{ route('army-lists.create') }}" class="link">Créer une première liste</a>.
        </p>
    @else
        <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($analyses as $analysis)
                @php($armyList = $analysis->armyList)
                <li class="card flex flex-col gap-3">
                    <div>
                        <a href="{{ route('army-lists.show', $armyList) }}" class="link text-lg font-semibold">{{ $armyList->name }}</a>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">
                            {{ $armyList->faction->fullName() }}@if ($armyList->detachment) · {{ $armyList->detachment->name }}@endif
                        </p>
                    </div>
                    <p class="text-2xl font-semibold">
                        {{ $analysis->totalPoints() }} <span class="text-base font-normal text-zinc-500">/ {{ $analysis->pointsLimit() }} pts · {{ $armyList->gameFormat->name }}</span>
                    </p>
                    @include('army-lists._status-badges')
                </li>
            @endforeach
        </ul>
    @endif
@endsection
