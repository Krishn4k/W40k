@extends('layouts.app')

@section('title', 'Traductions')

@section('content')
    @include('admin._nav')

    <div class="mb-6">
        <h1 class="text-2xl font-semibold">Traductions du référentiel</h1>
        <p class="text-sm text-zinc-600 dark:text-zinc-400">
            Noms français affichés dans l'interface ; sans traduction, le nom anglais est utilisé.
            Travail en masse : <code>php artisan reference:export-translations --missing</code>, puis <code>reference:import-translations</code>.
        </p>
    </div>

    <div class="mb-6 grid gap-3 sm:grid-cols-5">
        @foreach (App\Http\Controllers\Admin\TranslationController::TYPE_LABELS as $typeKey => $typeLabel)
            @php($typeProgress = $progress[$typeKey])
            <a href="{{ route('admin.translations.index', ['type' => $typeKey, 'status' => $status]) }}"
               @class(['card p-4', 'ring-2 ring-amber-600' => $type === $typeKey])>
                <p class="text-sm font-medium">{{ $typeLabel }}</p>
                <p class="text-xl font-semibold">{{ $typeProgress['total'] > 0 ? intdiv($typeProgress['translated'] * 100, $typeProgress['total']) : 0 }} %</p>
                <p class="text-xs text-zinc-500">{{ $typeProgress['translated'] }} / {{ $typeProgress['total'] }} · {{ $typeProgress['toReview'] }} à vérifier</p>
            </a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('admin.translations.index') }}" x-data="autoSubmitFilters" class="mb-6 flex flex-wrap items-end gap-4">
        <input type="hidden" name="type" value="{{ $type }}">
        <div>
            <label for="status" class="form-label">Afficher</label>
            <select id="status" name="status" class="form-input">
                <option value="missing" @selected($status === 'missing')>Sans traduction</option>
                <option value="review" @selected($status === 'review')>À vérifier</option>
                <option value="all" @selected($status === 'all')>Tout</option>
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
            <label for="q" class="form-label">Nom anglais contient</label>
            <input id="q" name="q" type="search" value="{{ $search }}" class="form-input">
        </div>
        <noscript><button type="submit" class="btn-secondary">Filtrer</button></noscript>
    </form>

    @if ($records->isEmpty())
        <p class="card text-zinc-600 dark:text-zinc-400">Rien à afficher avec ces filtres.</p>
    @else
        <ul class="card flex flex-col divide-y divide-zinc-200 p-0 dark:divide-zinc-800">
            @foreach ($records as $record)
                @php($toReview = $record->translationReview !== null)
                <li class="px-4 py-3">
                    <form method="POST" action="{{ route('admin.translations.update', [$type, $record->id]) }}" class="grid items-center gap-2 md:grid-cols-[1fr_1fr_auto_auto]">
                        @csrf
                        @method('PUT')
                        <span class="text-sm font-medium">
                            {{ $record->name->get('en') }}
                            @if ($toReview)
                                <span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800 dark:bg-amber-950 dark:text-amber-300">À vérifier</span>
                            @endif
                        </span>
                        <input type="text" name="fr" maxlength="255" aria-label="Nom français de {{ $record->name->get('en') }}"
                               value="{{ $record->name->has('fr') ? $record->name->get('fr') : '' }}" class="form-input mt-0">
                        <label class="flex items-center gap-1.5 text-sm whitespace-nowrap">
                            <input type="hidden" name="verified" value="0">
                            <input type="checkbox" name="verified" value="1" @checked(! $toReview) class="rounded border-zinc-300 dark:border-zinc-700">
                            Vérifié
                        </label>
                        <button type="submit" class="btn-secondary">Enregistrer</button>
                    </form>
                </li>
            @endforeach
        </ul>
        <div class="mt-4">{{ $records->links() }}</div>
    @endif
@endsection
