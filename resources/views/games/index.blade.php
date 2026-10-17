@extends('layouts.app')

@section('title', 'Parties')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">Parties</h1>
        <a href="{{ route('games.create') }}" class="btn-primary">Nouvelle partie</a>
    </div>

    @if ($games->isEmpty())
        <p class="card text-zinc-600 dark:text-zinc-400">
            Aucune partie enregistrée. <a href="{{ route('games.create') }}" class="link">Enregistrer une première partie</a>.
        </p>
    @else
        @php($overall = $statistics->overall())
        <div class="mb-8 grid gap-4 sm:grid-cols-4">
            <div class="card">
                <p class="text-sm text-zinc-600 dark:text-zinc-400">Parties</p>
                <p class="text-3xl font-semibold">{{ $overall['games'] }}</p>
            </div>
            <div class="card">
                <p class="text-sm text-zinc-600 dark:text-zinc-400">Taux de victoire</p>
                <p class="text-3xl font-semibold">{{ $overall['winRate'] }} %</p>
            </div>
            <div class="card sm:col-span-2">
                <p class="text-sm text-zinc-600 dark:text-zinc-400">Bilan</p>
                <p class="text-3xl font-semibold">
                    <span class="text-emerald-700 dark:text-emerald-400">{{ $overall['wins'] }} V</span>
                    · <span class="text-zinc-600 dark:text-zinc-400">{{ $overall['draws'] }} E</span>
                    · <span class="text-red-700 dark:text-red-400">{{ $overall['losses'] }} D</span>
                </p>
            </div>
        </div>

        <div class="mb-8 grid gap-4 lg:grid-cols-2">
            @include('games._stats-table', ['title' => 'Par faction jouée', 'label' => 'Faction', 'rows' => $statistics->byFaction()])
            @include('games._stats-table', ['title' => 'Par faction adverse', 'label' => 'Faction', 'rows' => $statistics->byOpponentFaction()])
            @include('games._stats-table', ['title' => 'Par liste', 'label' => 'Liste', 'rows' => $statistics->byList()])
            @include('games._stats-table', ['title' => 'Par détachement', 'label' => 'Détachement', 'rows' => $statistics->byDetachment()])
        </div>

        <h2 class="mb-3 text-lg font-semibold">Historique</h2>
        <div class="card overflow-x-auto p-0">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-zinc-200 text-xs text-zinc-500 uppercase dark:border-zinc-800">
                    <tr>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Résultat</th>
                        <th class="px-4 py-3">Ma liste</th>
                        <th class="px-4 py-3">Adversaire</th>
                        <th class="hidden px-4 py-3 md:table-cell">Mission</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @foreach ($games as $game)
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap"><a href="{{ route('games.show', $game) }}" class="link">{{ $game->played_on->format('d/m/Y') }}</a></td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $game->result->badgeClasses() }}">{{ $game->result->label() }}</span>
                                @if ($game->score_for !== null && $game->score_against !== null)
                                    <span class="ml-1 text-zinc-600 dark:text-zinc-400">{{ $game->score_for }}–{{ $game->score_against }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                {{ $game->list_name ?? '—' }}
                                @if ($game->faction)
                                    <span class="block text-xs text-zinc-500">{{ $game->faction->fullName() }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                {{ $game->opponent_name ?? '—' }}
                                @if ($game->opponentFaction)
                                    <span class="block text-xs text-zinc-500">{{ $game->opponentFaction->fullName() }}</span>
                                @endif
                            </td>
                            <td class="hidden px-4 py-3 md:table-cell">{{ $game->mission ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
