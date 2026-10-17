@extends('layouts.app')

@section('title', 'Collection')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">Collection</h1>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $totalModels }} figurine(s) au total.</p>
        </div>
        <a href="{{ route('collection.create') }}" class="btn-primary">Ajouter un lot</a>
    </div>

    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <form method="GET" action="{{ route('collection.index') }}" class="flex items-end gap-2">
            <div>
                <label for="faction" class="form-label">Faction</label>
                <select id="faction" name="faction" class="form-input" onchange="this.form.submit()">
                    <option value="">Toutes</option>
                    @foreach ($factionOptions as $id => $label)
                        <option value="{{ $id }}" @selected($selectedFactionId === $id)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <noscript><button type="submit" class="btn-secondary">Filtrer</button></noscript>
        </form>
        @include('partials.paint-legend')
    </div>

    @forelse ($lotsByFaction as $factionName => $lots)
        <section class="mb-8">
            <h2 class="mb-3 text-lg font-semibold">{{ $factionName }} <span class="text-sm font-normal text-zinc-500">({{ $lots->sum('quantity') }} fig.)</span></h2>
            <div class="card overflow-x-auto p-0">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-zinc-200 text-xs text-zinc-500 uppercase dark:border-zinc-800">
                        <tr>
                            <th class="px-4 py-3">Unité</th>
                            <th class="px-4 py-3 text-right">Quantité</th>
                            <th class="w-1/3 px-4 py-3">Peinture</th>
                            <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @foreach ($lots as $lot)
                            @php($counts = $lot->statusCounts->mapWithKeys(fn ($statusCount) => [$statusCount->status->value => $statusCount->count])->all())
                            <tr>
                                <td class="px-4 py-3">
                                    <span class="font-medium">{{ $lot->unit->name }}</span>
                                    @if ($lot->label)
                                        <span class="text-zinc-500">— {{ $lot->label }}</span>
                                    @endif
                                    @if ($lot->countsAsUnit)
                                        <span class="block text-xs text-amber-700 dark:text-amber-400">Joué comme {{ $lot->countsAsUnit->name }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-medium">{{ $lot->quantity }}</td>
                                <td class="px-4 py-3">
                                    @include('partials.paint-progress', ['counts' => $counts])
                                    <span class="mt-1 block text-xs text-zinc-500">
                                        {{ $lot->countAtLeast(App\Enums\PaintStatus::Painted) }}/{{ $lot->quantity }} peinte(s)
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('collection.edit', $lot) }}" class="link">Modifier</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @empty
        <p class="card text-zinc-600 dark:text-zinc-400">
            Aucune figurine pour l'instant. <a href="{{ route('collection.create') }}" class="link">Ajouter un premier lot</a>.
        </p>
    @endforelse
@endsection
