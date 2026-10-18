@extends('layouts.app')

@section('title', 'Partie du '.$game->played_on->format('d/m/Y'))

@section('content')
    <p class="text-sm"><a href="{{ route('games.index') }}" class="link">Parties</a></p>

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">
                Partie du {{ $game->played_on->translatedFormat('j F Y') }}
                <span class="ml-2 rounded-full px-2.5 py-1 align-middle text-sm font-medium {{ $game->result->badgeClasses() }}">{{ $game->result->label() }}</span>
            </h1>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                @if ($game->score_for !== null && $game->score_against !== null)
                    Score {{ $game->score_for }} – {{ $game->score_against }} ·
                @endif
                {{ $game->mission ?? 'Mission non renseignée' }}
            </p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('games.edit', $game) }}" class="btn-secondary">Modifier</a>
            <form method="POST" action="{{ route('games.destroy', $game) }}" onsubmit="return confirm('Supprimer cette partie ?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-danger">Supprimer</button>
            </form>
        </div>
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-2">
        <section class="card">
            <h2 class="mb-2 font-semibold">Mon armée</h2>
            <p>{{ $game->faction?->fullName() ?? 'Faction non renseignée' }}</p>
            @if ($game->detachment)
                <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $game->detachment->name }}</p>
            @endif
            @if ($game->force_disposition)
                <p class="text-sm text-zinc-600 dark:text-zinc-400">Disposition : {{ $game->force_disposition->label() }} ({{ $game->force_disposition->englishName() }})</p>
            @endif
            @if ($game->list_name)
                <p class="mt-2 text-sm">
                    Liste « {{ $game->list_name }} » — {{ $game->list_points }} pts
                    @if ($game->armyList)
                        · <a href="{{ route('army-lists.show', $game->armyList) }}" class="link">voir la liste actuelle</a>
                    @else
                        · liste supprimée depuis
                    @endif
                </p>
            @endif
        </section>
        <section class="card">
            <h2 class="mb-2 font-semibold">Adversaire</h2>
            <p>{{ $game->opponent_name ?? 'Non renseigné' }}</p>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $game->opponentFaction?->fullName() ?? 'Faction non renseignée' }}</p>
            @if ($game->opponent_force_disposition)
                <p class="text-sm text-zinc-600 dark:text-zinc-400">Disposition : {{ $game->opponent_force_disposition->label() }} ({{ $game->opponent_force_disposition->englishName() }})</p>
            @endif
        </section>
    </div>

    @if ($game->list_snapshot)
        <section class="mb-6">
            <h2 class="mb-2 text-lg font-semibold">Liste jouée (copie au moment de la partie)</h2>
            <pre class="card overflow-x-auto font-mono text-xs whitespace-pre-wrap">{{ $game->list_snapshot }}</pre>
        </section>
    @endif

    @if ($game->notes)
        <section>
            <h2 class="mb-2 text-lg font-semibold">Notes</h2>
            <p class="text-sm whitespace-pre-line text-zinc-700 dark:text-zinc-300">{{ $game->notes }}</p>
        </section>
    @endif
@endsection
