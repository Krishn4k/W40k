@extends('layouts.app')

@section('title', 'Versions des points')

@section('content')
    @include('partials.errors', ['keys' => ['point_set']])

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <p class="text-sm"><a href="{{ route('factions.index') }}" class="link">Référentiel</a></p>
            <h1 class="text-2xl font-semibold">Versions des points</h1>
        </div>
        <a href="{{ route('point-sets.create') }}" class="btn-primary">Nouvelle version</a>
    </div>

    @if ($pointSets->isEmpty())
        <p class="card text-zinc-600 dark:text-zinc-400">Aucune version de points.</p>
    @else
        <div class="card overflow-x-auto p-0">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-zinc-200 text-xs text-zinc-500 uppercase dark:border-zinc-800">
                    <tr>
                        <th class="px-4 py-3">Nom</th>
                        <th class="px-4 py-3">Période</th>
                        <th class="px-4 py-3 text-right">Coûts d'unités</th>
                        <th class="px-4 py-3 text-right">Listes figées</th>
                        <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @foreach ($pointSets as $pointSet)
                        <tr>
                            <td class="px-4 py-3 font-medium">
                                {{ $pointSet->name }}
                                @if ($currentPointSet?->is($pointSet))
                                    <span class="ml-1 rounded bg-emerald-100 px-1.5 py-0.5 text-xs text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">En vigueur</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                du {{ $pointSet->valid_from->format('d/m/Y') }}{{ $pointSet->valid_to ? ' au '.$pointSet->valid_to->format('d/m/Y') : '' }}
                            </td>
                            <td class="px-4 py-3 text-right">{{ $pointSet->unit_point_costs_count }}</td>
                            <td class="px-4 py-3 text-right">{{ $pointSet->army_lists_count }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('point-sets.edit', $pointSet) }}" class="link">Modifier</a>
                                <form method="POST" action="{{ route('point-sets.destroy', $pointSet) }}" class="ml-3 inline" onsubmit="return confirm('Supprimer cette version et tous ses coûts ?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="link">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
