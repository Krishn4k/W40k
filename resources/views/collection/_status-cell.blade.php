{{--
    Cellule d'un statut de peinture dans une ligne de lot : nombre, − / +, et » pour passer une figurine au statut suivant.
    Attend $status (value, label), $isLast et $count (valeur initiale, affichée sans JavaScript).
--}}
<td class="px-2 py-3 text-center">
    <div class="inline-flex items-center gap-0.5">
        <button type="button" class="status-step" @click="change('{{ $status['value'] }}', -1)" :disabled="saving || ! counts['{{ $status['value'] }}']"
                aria-label="Une figurine de moins : {{ $status['label'] }}">−</button>
        <span class="w-7 text-center font-medium tabular-nums" x-text="counts['{{ $status['value'] }}'] ?? 0">{{ $count }}</span>
        <button type="button" class="status-step" @click="change('{{ $status['value'] }}', 1)" :disabled="saving"
                aria-label="Une figurine de plus : {{ $status['label'] }}">+</button>
        @unless ($isLast)
            <button type="button" class="status-step" @click="advance('{{ $status['value'] }}')" :disabled="saving || ! counts['{{ $status['value'] }}']"
                    title="Passer une figurine au statut suivant" aria-label="Passer une figurine « {{ $status['label'] }} » au statut suivant">»</button>
        @endunless
    </div>
</td>
