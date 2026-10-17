{{-- Tableau de bilan par groupe. Attend $title, $label (en-tête de la 1re colonne) et $rows : [nom => bilan]. --}}
<section class="card overflow-x-auto p-0">
    <h3 class="px-4 pt-4 font-semibold">{{ $title }}</h3>
    <table class="w-full text-left text-sm">
        <thead class="border-b border-zinc-200 text-xs text-zinc-500 uppercase dark:border-zinc-800">
            <tr>
                <th class="px-4 py-2">{{ $label }}</th>
                <th class="px-4 py-2 text-right">Parties</th>
                <th class="px-4 py-2 text-right" title="Victoires / égalités / défaites">V / E / D</th>
                <th class="px-4 py-2 text-right">Victoires</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
            @foreach ($rows as $name => $tally)
                <tr>
                    <td class="px-4 py-2">{{ $name }}</td>
                    <td class="px-4 py-2 text-right">{{ $tally['games'] }}</td>
                    <td class="px-4 py-2 text-right whitespace-nowrap">{{ $tally['wins'] }} / {{ $tally['draws'] }} / {{ $tally['losses'] }}</td>
                    <td class="px-4 py-2 text-right font-medium">{{ $tally['winRate'] }} %</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</section>
