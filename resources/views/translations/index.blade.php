@extends('layouts.app')

@section('title', 'Traductions — liste de travail')

@section('content')
    @include('translations._nav')

    <div class="mb-4 flex flex-wrap gap-2">
        @foreach (App\Http\Controllers\Admin\TranslationController::TYPE_LABELS as $typeKey => $typeLabel)
            <a href="{{ route('translations.index', ['type' => $typeKey, 'status' => $status]) }}"
               @class([
                   'rounded-full border px-3 py-1 text-sm',
                   'border-amber-600 bg-amber-50 font-semibold text-amber-800 dark:bg-amber-950 dark:text-amber-300' => $type === $typeKey,
                   'border-zinc-300 hover:border-amber-600 dark:border-zinc-700' => $type !== $typeKey,
               ])>{{ $typeLabel }}</a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('translations.index') }}" x-data="autoSubmitFilters" data-results="#translation-results" class="mb-6 flex flex-wrap items-end gap-4">
        <input type="hidden" name="type" value="{{ $type }}">
        <div>
            <label for="status" class="form-label">Statut</label>
            <select id="status" name="status" class="form-input">
                @foreach (['missing' => 'À traduire', 'review' => 'À vérifier', 'validated' => 'Validés', 'all' => 'Tout'] as $statusKey => $statusLabel)
                    <option value="{{ $statusKey }}" @selected($status === $statusKey)>{{ $statusLabel }}</option>
                @endforeach
            </select>
        </div>
        @if (in_array($type, ['unit', 'detachment', 'enhancement'], true))
            <div>
                <label for="faction" class="form-label">Faction</label>
                <select id="faction" name="faction" class="form-input">
                    <option value="">Toutes</option>
                    @foreach ($factionOptions as $id => $label)
                        <option value="{{ $id }}" @selected($factionId === $id)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div>
            <label for="q" class="form-label">Nom (anglais ou français) contient</label>
            <input id="q" name="q" type="search" value="{{ $search }}" class="form-input">
        </div>
        <noscript><button type="submit" class="btn-secondary">Filtrer</button></noscript>
    </form>

    <div id="translation-results" class="aria-busy:opacity-60">
        <p class="mb-2 text-sm text-zinc-600 dark:text-zinc-400">{{ $records->total() }} nom(s)</p>

        @if ($records->isEmpty())
            <p class="card text-zinc-600 dark:text-zinc-400">Rien à afficher avec ces filtres.</p>
        @else
            <form id="bulk-validate" method="POST" action="{{ route('translations.validate', $type) }}">
                @csrf
            </form>

            <div x-data="{ toggleAll(checked) { document.querySelectorAll('input[form=bulk-validate]').forEach((box) => box.checked = checked) } }"
                 class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" @change="toggleAll($event.target.checked)" class="rounded border-zinc-300 dark:border-zinc-700">
                    Tout cocher (cette page)
                </label>
                <button type="submit" form="bulk-validate" class="btn-secondary">Valider la sélection</button>
            </div>

            <ul class="card flex flex-col divide-y divide-zinc-200 p-0 dark:divide-zinc-800">
                @foreach ($records as $record)
                    @php
                        $recordStatus = ! $record->name->has('fr') ? 'missing' : ($record->translationReview ? 'review' : 'validated');
                        $contextUrl = match ($type) {
                            'unit' => route('units.edit', $record),
                            'detachment' => route('detachments.edit', $record),
                            'enhancement' => route('detachments.edit', $record->detachment_id),
                            'faction' => route('factions.show', $record),
                            default => null,
                        };
                    @endphp
                    <li x-data="translationRow(@js([
                            'status' => $recordStatus,
                            'statusLabel' => App\Http\Controllers\Admin\TranslationController::STATUS_LABELS[$recordStatus],
                            'fr' => $record->name->has('fr') ? $record->name->get('fr') : '',
                        ]))"
                        class="px-4 py-3">
                        <form method="POST" action="{{ route('translations.update', [$type, $record->id]) }}" @submit.prevent="save($el)"
                              class="grid items-center gap-2 md:grid-cols-[auto_1fr_1fr_auto]">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="verified" value="1">
                            <input type="checkbox" form="bulk-validate" name="ids[]" value="{{ $record->id }}"
                                   aria-label="Sélectionner {{ $record->name->get('en') }}" class="rounded border-zinc-300 dark:border-zinc-700">
                            <span class="text-sm">
                                <span class="font-medium">{{ $record->name->get('en') }}</span>
                                <span class="ml-1 rounded px-1.5 py-0.5 text-xs"
                                      :class="{
                                          'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300': status === 'missing',
                                          'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300': status === 'review',
                                          'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300': status === 'validated',
                                      }"
                                      x-text="statusLabel">{{ App\Http\Controllers\Admin\TranslationController::STATUS_LABELS[$recordStatus] }}</span>
                                @if ($contextUrl)
                                    <a href="{{ $contextUrl }}" target="_blank" class="link ml-1 text-xs">fiche ↗</a>
                                @endif
                            </span>
                            <input type="text" name="fr" maxlength="255" x-model="fr" aria-label="Nom français de {{ $record->name->get('en') }}"
                                   value="{{ $record->name->has('fr') ? $record->name->get('fr') : '' }}" class="form-input mt-0">
                            <button type="submit" class="btn-secondary" :disabled="saving" x-text="saving ? '…' : 'Valider'">Valider</button>
                            <p x-show="error" x-text="error" class="form-error md:col-start-3"></p>
                        </form>
                    </li>
                @endforeach
            </ul>
            <div class="mt-4">{{ $records->links() }}</div>
        @endif
    </div>
@endsection
