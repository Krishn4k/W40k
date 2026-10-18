@extends('layouts.app')

@section('title', $armyList->name)

@section('content')
    <p class="text-sm"><a href="{{ route('army-lists.index') }}" class="link">Listes d'armée</a></p>

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">{{ $armyList->name }}</h1>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                {{ $armyList->faction->fullName() }}
                · {{ $armyList->detachments->isNotEmpty() ? $armyList->detachmentNames() : 'Détachement à choisir' }}
                @if ($armyList->detachments->isNotEmpty())
                    ({{ $analysis->detachmentPoints() }}{{ $analysis->detachmentPointsLimit() !== null ? ' / '.$analysis->detachmentPointsLimit() : '' }} DP)
                @endif
                · {{ $armyList->gameFormat->name }}
                · {{ $analysis->pointSet ? 'Points : '.$analysis->pointSet->name.($armyList->point_set_id ? ' (figée)' : '') : 'Aucune version de points' }}
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('army-lists.edit', $armyList) }}" class="btn-secondary">Modifier</a>
            <form method="POST" action="{{ route('army-lists.duplicate', $armyList) }}">
                @csrf
                <button type="submit" class="btn-secondary">Dupliquer</button>
            </form>
            <a href="{{ route('army-lists.export', $armyList) }}" target="_blank" class="btn-secondary">Export texte</a>
            <a href="{{ route('games.create', ['army_list_id' => $armyList->id]) }}" class="btn-secondary">Enregistrer une partie</a>
            <form method="POST" action="{{ route('army-lists.destroy', $armyList) }}" onsubmit="return confirm('Supprimer cette liste ?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-danger">Supprimer</button>
            </form>
        </div>
    </div>

    @php($total = $analysis->totalPoints())
    @php($limit = $analysis->pointsLimit())
    <div class="card mb-6 flex flex-col gap-3">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <p class="text-3xl font-semibold">{{ $total }} <span class="text-base font-normal text-zinc-500">/ {{ $limit }} pts</span></p>
            @include('army-lists._status-badges')
        </div>
        <div class="h-2.5 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
            <div @class(['h-full', 'bg-amber-600' => $total <= $limit, 'bg-red-600' => $total > $limit]) style="width: {{ min(100, $limit > 0 ? round($total / $limit * 100, 2) : 0) }}%"></div>
        </div>
    </div>

    @if ($analysis->violations() !== [])
        <section role="alert" class="mb-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
            <h2 class="mb-1 font-semibold">Règles de composition non respectées</h2>
            <ul class="list-inside list-disc">
                @foreach ($analysis->violations() as $violation)
                    <li>
                        {{ $violation['message'] }}
                        @if ($violation['details'] !== [])
                            <span class="text-red-700 dark:text-red-300">({{ implode(' ; ', $violation['details']) }})</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="mb-8">
        <h2 class="mb-3 text-lg font-semibold">Unités ({{ $armyList->entries->count() }})</h2>

        @if ($armyList->entries->isEmpty())
            <p class="card text-zinc-600 dark:text-zinc-400">Aucune unité : ajoute la première ci-dessous.</p>
        @else
            <ul class="flex flex-col gap-3">
                @foreach ($armyList->entries as $entry)
                    @php($playability = $analysis->playability($entry))
                    <li x-data="{ editing: {{ old('_form') === (string) $entry->id ? 'true' : 'false' }} }" class="card p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-medium">
                                    @if ($entry->is_warlord)
                                        <span title="Seigneur de guerre" class="text-amber-600">★</span>
                                    @endif
                                    {{ $entry->unit->name }}
                                    @if ($entry->model_count > 1)
                                        <span class="text-zinc-500">×{{ $entry->model_count }}</span>
                                    @endif
                                    <span class="ml-2 font-semibold">{{ $analysis->entryPoints($entry) }} pts</span>
                                </p>
                                @if ($analysis->copyRank($entry) > 1)
                                    <p class="text-xs text-zinc-500">{{ $analysis->copyRank($entry) }}e exemplaire de cette unité</p>
                                @endif
                                @if ($entry->attachedTo)
                                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Rattachée à : {{ $entry->attachedTo->unit->name }}</p>
                                @endif
                                @if ($entry->enhancement)
                                    <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $entry->enhancement->is_upgrade ? 'Upgrade' : 'Optimisation' }} : {{ $entry->enhancement->name }} (+{{ $analysis->enhancementPoints($entry) }} pts)</p>
                                @endif
                                @if ($entry->wargear->isNotEmpty())
                                    <p class="text-sm text-zinc-600 dark:text-zinc-400">
                                        Équipement : {{ $entry->wargear->map(fn ($wargear) => $wargear->name.' ×'.$wargear->quantity)->join(', ') }}
                                        (+{{ $analysis->wargearPoints($entry) }} pts)
                                    </p>
                                @endif
                                <p @class([
                                    'mt-1 text-sm',
                                    'text-emerald-700 dark:text-emerald-400' => $playability['status'] === 'ok',
                                    'text-amber-700 dark:text-amber-400' => $playability['status'] !== 'ok',
                                ])>
                                    @if ($entry->collectionLot)
                                        Lot : {{ $entry->collectionLot->label ?: $entry->collectionLot->unit->name }}{{ $entry->collectionLot->counts_as_unit_id ? ' (proxy)' : '' }} —
                                    @endif
                                    {{ $playability['message'] }}
                                </p>
                            </div>
                            <div class="flex gap-3 text-sm">
                                <button type="button" class="link" @click="editing = ! editing" x-text="editing ? 'Fermer' : 'Modifier'">Modifier</button>
                                <form method="POST" action="{{ route('army-lists.entries.destroy', [$armyList, $entry]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="link">Retirer</button>
                                </form>
                            </div>
                        </div>
                        <div x-show="editing" x-cloak class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                            @include('army-lists._entry-form', [
                                'action' => route('army-lists.entries.update', [$armyList, $entry]),
                                'entry' => $entry,
                            ])
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="card">
        <h2 class="mb-4 text-lg font-semibold">Ajouter une unité</h2>
        @include('army-lists._entry-form', ['action' => route('army-lists.entries.store', $armyList), 'entry' => null])
    </section>

    @if ($armyList->notes)
        <section class="mt-8">
            <h2 class="mb-2 text-lg font-semibold">Notes</h2>
            <p class="text-sm whitespace-pre-line text-zinc-700 dark:text-zinc-300">{{ $armyList->notes }}</p>
        </section>
    @endif
@endsection
