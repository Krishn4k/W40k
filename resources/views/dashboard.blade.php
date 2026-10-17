@extends('layouts.app')

@section('title', 'Tableau de bord')

@section('content')
    <h1 class="mb-6 text-2xl font-semibold">Tableau de bord</h1>

    @if ($overall['models'] === 0)
        <div class="card">
            <p class="text-zinc-600 dark:text-zinc-400">Ta collection est vide pour l'instant.</p>
            <a href="{{ route('collection.create') }}" class="btn-primary mt-4">Ajouter un premier lot</a>
        </div>
    @else
        <div class="mb-8 grid gap-4 sm:grid-cols-3">
            <div class="card">
                <p class="text-sm text-zinc-600 dark:text-zinc-400">Figurines</p>
                <p class="text-3xl font-semibold">{{ $overall['models'] }}</p>
            </div>
            <div class="card">
                <p class="text-sm text-zinc-600 dark:text-zinc-400">Peintes</p>
                <p class="text-3xl font-semibold">{{ App\Support\CollectionProgress::paintedPercent($overall) }} %</p>
                <p class="text-sm text-zinc-500">{{ $overall['painted'] }} / {{ $overall['models'] }} figurines</p>
            </div>
            <div class="card">
                <p class="text-sm text-zinc-600 dark:text-zinc-400">Points peints</p>
                <p class="text-3xl font-semibold">{{ $overall['paintedPoints'] }} <span class="text-base font-normal text-zinc-500">/ {{ $overall['points'] }} pts</span></p>
                <p class="text-xs text-zinc-500">Estimation{{ $pointSet ? ' ('.$pointSet->name.')' : ' : aucune version de points en vigueur' }}</p>
            </div>
        </div>

        <section class="card mb-8">
            <h2 class="mb-3 font-semibold">Répartition par statut</h2>
            @include('partials.paint-progress', ['counts' => $overall['statusCounts']])
            <ul class="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-sm">
                @foreach (App\Enums\PaintStatus::cases() as $status)
                    <li class="flex items-center gap-1.5">
                        <span class="inline-block size-2.5 rounded-full {{ $status->colorClasses() }}"></span>
                        {{ $status->label() }} : <strong>{{ $overall['statusCounts'][$status->value] ?? 0 }}</strong>
                    </li>
                @endforeach
            </ul>
        </section>

        <section>
            <h2 class="mb-3 text-lg font-semibold">Par faction</h2>
            <div class="card overflow-x-auto p-0">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-zinc-200 text-xs text-zinc-500 uppercase dark:border-zinc-800">
                        <tr>
                            <th class="px-4 py-3">Faction</th>
                            <th class="px-4 py-3 text-right">Figurines</th>
                            <th class="w-1/3 px-4 py-3">Peinture</th>
                            <th class="px-4 py-3 text-right">Peintes</th>
                            <th class="px-4 py-3 text-right">Points peints</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @foreach ($byFaction as $factionName => $progress)
                            <tr>
                                <td class="px-4 py-3 font-medium">{{ $factionName }}</td>
                                <td class="px-4 py-3 text-right">{{ $progress['models'] }}</td>
                                <td class="px-4 py-3">@include('partials.paint-progress', ['counts' => $progress['statusCounts']])</td>
                                <td class="px-4 py-3 text-right">{{ App\Support\CollectionProgress::paintedPercent($progress) }} %</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">{{ $progress['paintedPoints'] }} / {{ $progress['points'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
