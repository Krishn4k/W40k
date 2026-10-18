@extends('layouts.app')

@section('title', 'Traductions')

@section('content')
    @include('translations._nav')

    <div class="mb-6">
        <h1 class="text-2xl font-semibold">Traductions du référentiel</h1>
        <p class="text-sm text-zinc-600 dark:text-zinc-400">
            Ce qu'il reste à traduire et à vérifier. Cliquer sur un nombre ouvre la liste de travail correspondante.
        </p>
    </div>

    <div class="mb-8 grid gap-4 sm:grid-cols-3">
        @foreach ([
            ['missing', 'À traduire', 'text-red-700 dark:text-red-400'],
            ['review', 'À vérifier', 'text-amber-700 dark:text-amber-400'],
            ['validated', 'Validés', 'text-emerald-700 dark:text-emerald-400'],
        ] as [$statusKey, $label, $color])
            <div class="card">
                <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $label }}</p>
                <p class="text-3xl font-semibold {{ $color }}">{{ $grandTotal[$statusKey] }}</p>
                <p class="text-xs text-zinc-500">sur {{ $grandTotal['total'] }} noms</p>
            </div>
        @endforeach
    </div>

    <h2 class="mb-3 text-lg font-semibold">Par type</h2>
    <div class="mb-8 grid gap-3 sm:grid-cols-5">
        @foreach (App\Http\Controllers\Admin\TranslationController::TYPE_LABELS as $typeKey => $typeLabel)
            @php($counts = $totals[$typeKey])
            <div class="card flex flex-col gap-2 p-4">
                <p class="text-sm font-medium">{{ $typeLabel }}</p>
                @include('translations._progress', ['counts' => $counts])
                <p class="flex flex-wrap gap-x-3 text-xs">
                    <a href="{{ route('translations.index', ['type' => $typeKey, 'status' => 'missing']) }}" class="text-red-700 hover:underline dark:text-red-400">{{ $counts['missing'] }} à traduire</a>
                    <a href="{{ route('translations.index', ['type' => $typeKey, 'status' => 'review']) }}" class="text-amber-700 hover:underline dark:text-amber-400">{{ $counts['review'] }} à vérifier</a>
                    <span class="text-zinc-500">{{ $counts['validated'] }} validés</span>
                </p>
            </div>
        @endforeach
    </div>

    <h2 class="mb-3 text-lg font-semibold">Par faction</h2>
    <div class="card overflow-x-auto p-0">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-zinc-200 text-xs text-zinc-500 uppercase dark:border-zinc-800">
                <tr>
                    <th class="px-4 py-3">Faction</th>
                    <th class="px-4 py-3 text-right">Reste</th>
                    @foreach (['unit' => 'Unités', 'detachment' => 'Détachements', 'enhancement' => 'Optimisations'] as $label)
                        <th class="px-4 py-3">{{ $label }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @foreach ($rows as $row)
                    <tr @class(['text-zinc-400 dark:text-zinc-600' => $row['remaining'] === 0])>
                        <td class="px-4 py-3 font-medium">{{ $row['faction']->fullName() }}</td>
                        <td class="px-4 py-3 text-right font-semibold">{{ $row['remaining'] ?: '✓' }}</td>
                        @foreach ($row['cells'] as $typeKey => $counts)
                            <td class="min-w-40 px-4 py-3">
                                @if ($counts['total'] === 0)
                                    <span class="text-zinc-400">—</span>
                                @else
                                    @include('translations._progress', ['counts' => $counts])
                                    <span class="mt-1 flex flex-wrap gap-x-2 text-xs">
                                        @if ($counts['missing'] > 0)
                                            <a href="{{ route('translations.index', ['type' => $typeKey, 'status' => 'missing', 'faction' => $row['faction']->id]) }}" class="text-red-700 hover:underline dark:text-red-400">{{ $counts['missing'] }} à traduire</a>
                                        @endif
                                        @if ($counts['review'] > 0)
                                            <a href="{{ route('translations.index', ['type' => $typeKey, 'status' => 'review', 'faction' => $row['faction']->id]) }}" class="text-amber-700 hover:underline dark:text-amber-400">{{ $counts['review'] }} à vérifier</a>
                                        @endif
                                        @if ($counts['missing'] + $counts['review'] === 0)
                                            <span class="text-emerald-700 dark:text-emerald-400">{{ $counts['validated'] }} validés</span>
                                        @endif
                                    </span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="mt-6 text-xs text-zinc-500">
        Travail en masse dans un tableur : <code>php artisan reference:export-translations --missing</code>, puis
        <code>php artisan reference:import-translations</code>. Pour conserver les validations faites ici dans le fichier versionné,
        relancer <code>reference:export-translations</code> et commiter <code>database/translations/fr.csv</code>.
    </p>
@endsection
