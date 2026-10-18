{{-- Tableau d'unités : nom, faction d'origine, rôle, coûts dans la version de points en vigueur, mots-clés. --}}
<div class="card overflow-x-auto p-0">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-zinc-200 text-xs text-zinc-500 uppercase dark:border-zinc-800">
            <tr>
                <th class="px-4 py-3" @if (($sort ?? 'name') === 'name') aria-sort="{{ ($direction ?? 'asc') === 'asc' ? 'ascending' : 'descending' }}" @endif>
                    @include('units._sort-link', ['label' => 'Unité', 'param' => 'nom', 'column' => 'name'])
                </th>
                <th class="px-4 py-3" @if (($sort ?? '') === 'role') aria-sort="{{ $direction === 'asc' ? 'ascending' : 'descending' }}" @endif>
                    @include('units._sort-link', ['label' => 'Rôle', 'param' => 'role', 'column' => 'role'])
                </th>
                <th class="px-4 py-3" @if (($sort ?? '') === 'points') aria-sort="{{ $direction === 'asc' ? 'ascending' : 'descending' }}" @endif>
                    @include('units._sort-link', ['label' => 'Points', 'param' => 'points', 'column' => 'points'])
                </th>
                <th class="hidden px-4 py-3 md:table-cell">Mots-clés</th>
                <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
            @foreach ($units as $unit)
                <tr>
                    <td class="px-4 py-3">
                        <span class="font-medium">{{ $unit->name }}</span>
                        @if ($unit->is_epic_hero)
                            <span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800 dark:bg-amber-950 dark:text-amber-300">Héros épique</span>
                        @endif
                        @if ($unit->name->get('en') !== (string) $unit->name)
                            <span class="block text-xs text-zinc-500">{{ $unit->name->get('en') }}</span>
                        @endif
                        @if (! isset($currentFaction) || $unit->faction_id !== $currentFaction->id)
                            <span class="block text-xs text-zinc-500">{{ isset($currentFaction) ? 'Héritée de ' : '' }}{{ $unit->faction->name }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">{{ $unit->role->label() }}</td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        @forelse ($unit->pointCosts->groupBy('model_count') as $modelCount => $tiers)
                            <span class="block">
                                {{ $modelCount }} fig. : <strong>{{ $tiers->firstWhere('from_copy', 1)?->points ?? $tiers->first()->points }}</strong> pts
                                @foreach ($tiers->where('from_copy', '>', 1)->sortBy('from_copy') as $tier)
                                    <span class="text-xs text-zinc-500" title="Seuil de réquisition : prix à partir de la {{ $tier->from_copy }}e unité identique">· {{ $tier->from_copy }}e+ : {{ $tier->points }}</span>
                                @endforeach
                            </span>
                        @empty
                            <span class="text-zinc-400">—</span>
                        @endforelse
                    </td>
                    <td class="hidden px-4 py-3 text-xs text-zinc-600 md:table-cell dark:text-zinc-400">
                        {{ $unit->keywords->map(fn ($keyword) => (string) $keyword->name)->sort()->join(', ') }}
                    </td>
                    <td class="px-4 py-3 text-right">
                        @can('admin')
                            <a href="{{ route('units.edit', $unit) }}" class="link">Modifier</a>
                        @endcan
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
