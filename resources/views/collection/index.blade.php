@extends('layouts.app')

@section('title', 'Collection')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">Collection</h1>
            <p id="collection-summary" data-live-stats class="text-sm text-zinc-600 dark:text-zinc-400">{{ $totalModels }} figurine(s) au total, {{ count($ownedFactions) }} faction(s).</p>
        </div>
        <a href="{{ route('collection.create') }}" class="btn-primary">Ajouter un lot</a>
    </div>

    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <form method="GET" action="{{ route('collection.index') }}" x-data="autoSubmitFilters" data-results="#collection-results" class="flex flex-wrap items-end gap-2">
            <fieldset id="collection-faction-chips" data-live-stats class="flex flex-wrap gap-2">
                <legend class="sr-only">Faction</legend>
                <label class="faction-chip">
                    <input type="radio" name="faction" value="" class="sr-only" @checked($selectedFactionId === null)>
                    Toutes <span class="faction-chip-count">{{ $totalModels }}</span>
                </label>
                @foreach ($ownedFactions as $id => $faction)
                    <label class="faction-chip">
                        <input type="radio" name="faction" value="{{ $id }}" class="sr-only" @checked($selectedFactionId === $id)>
                        {{ $faction['name'] }} <span class="faction-chip-count">{{ $faction['models'] }}</span>
                    </label>
                @endforeach
            </fieldset>
            <noscript><button type="submit" class="btn-secondary">Filtrer</button></noscript>
        </form>
        @include('partials.paint-legend')
    </div>

    <div id="collection-results" class="aria-busy:opacity-60">
    {{-- Zones marquées data-live-stats : rechargées depuis le serveur après chaque modification sur place. --}}
    <div id="collection-stats" data-live-stats>
    @if ($progress['models'] > 0)
        <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
            @include('collection._stat-tile', ['label' => 'Points', 'value' => $progress['points'], 'detail' => 'estimés (version en vigueur)'])
            @include('collection._stat-tile', ['label' => 'Figurines', 'value' => $progress['models'], 'detail' => count($lotsByFaction).' faction(s) et chapitre(s)'])
            @include('collection._stat-tile', ['label' => 'Peintes', 'value' => $paintedPercent.' %', 'detail' => $progress['painted'].' / '.$progress['models'].' figurine(s)'])
            @include('collection._stat-tile', ['label' => 'Points peints', 'value' => $paintedPointsPercent.' %', 'detail' => $progress['paintedPoints'].' / '.$progress['points'].' pts'])
        </div>
    @endif
    </div>
    @php($statuses = collect(App\Enums\PaintStatus::cases())->map(fn ($status) => ['value' => $status->value, 'label' => $status->label(), 'color' => $status->colorClasses()])->values()->all())

    <form method="POST" action="{{ route('collection.store') }}" class="card mb-6 grid items-end gap-3 p-4 sm:grid-cols-[1fr_7rem_10rem_auto]">
        @csrf
        <input type="hidden" name="faction" value="{{ $selectedFactionId }}">
        @include('partials.filterable-unit-select', [
            'name' => 'unit_id',
            'id' => 'quick_unit_id',
            'label' => 'Ajout rapide',
            'options' => $quickAddUnitOptions,
            'selected' => '',
            'required' => true,
            'emptyLabel' => 'Choisir une unité…',
        ])
        <div x-data="{ quantity: 1, status: 'on_sprue' }" class="contents">
            <div>
                <label for="quick_quantity" class="form-label">Quantité</label>
                <input id="quick_quantity" type="number" min="1" max="999" required x-model.number="quantity" class="form-input">
            </div>
            <div>
                <label for="quick_status" class="form-label">Statut</label>
                <select id="quick_status" x-model="status" class="form-input">
                    @foreach ($statuses as $status)
                        <option value="{{ $status['value'] }}">{{ $status['label'] }}</option>
                    @endforeach
                </select>
                <input type="hidden" :name="`statuses[${status}]`" :value="quantity">
            </div>
        </div>
        <button type="submit" class="btn-primary">Ajouter</button>
    </form>

    @forelse ($lotsByFaction as $factionName => $lots)
        <section class="mb-8">
            <h2 class="mb-3 text-lg font-semibold">{{ $factionName }} <span id="faction-models-{{ $loop->index }}" data-live-stats class="text-sm font-normal text-zinc-500">({{ $lots->sum('quantity') }} fig.)</span></h2>
            <div class="card overflow-x-auto p-0">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-zinc-200 text-xs text-zinc-500 uppercase dark:border-zinc-800">
                        <tr>
                            <th class="px-4 py-3">Unité</th>
                            @foreach ($statuses as $status)
                                <th class="px-2 py-3 text-center whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1.5"><span class="inline-block size-2.5 rounded-full {{ $status['color'] }}"></span>{{ $status['label'] }}</span>
                                </th>
                            @endforeach
                            <th class="px-3 py-3 text-right">Total</th>
                            <th class="min-w-36 px-4 py-3">Progression</th>
                            <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @foreach ($lots as $lot)
                            @php($counts = $lot->statusCounts->mapWithKeys(fn ($statusCount) => [$statusCount->status->value => $statusCount->count])->all())
                            <tr x-data="lotStatusRow(@js((object) $counts), @js($statuses), '{{ route('collection.statuses.update', $lot) }}')"
                                :class="saving && 'opacity-70'">
                                <td class="px-4 py-3">
                                    <span class="font-medium">{{ $lot->unit->name }}</span>
                                    @if ($lot->label)
                                        <span class="text-zinc-500">— {{ $lot->label }}</span>
                                    @endif
                                    @if ($lot->countsAsUnit)
                                        <span class="block text-xs text-amber-700 dark:text-amber-400">Joué comme {{ $lot->countsAsUnit->name }}</span>
                                    @endif
                                    <span x-show="error" x-text="error" x-cloak class="form-error block"></span>
                                </td>
                                @foreach ($statuses as $index => $status)
                                    @include('collection._status-cell', ['status' => $status, 'isLast' => $index === count($statuses) - 1, 'count' => $counts[$status['value']] ?? 0])
                                @endforeach
                                <td class="px-3 py-3 text-right font-semibold" x-text="quantity">{{ $lot->quantity }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex h-2.5 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                        <template x-for="status in statuses" :key="status.value">
                                            <div :class="status.color" :style="`width: ${quantity ? (counts[status.value] ?? 0) / quantity * 100 : 0}%`"></div>
                                        </template>
                                    </div>
                                    <span class="mt-1 block text-xs text-zinc-500" x-text="`${painted}/${quantity} peinte(s)`">
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
    </div>
@endsection
