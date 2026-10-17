{{-- Badges de conformité et de jouabilité d'une liste. Attend $analysis. --}}
<div class="flex flex-wrap gap-2 text-xs font-medium">
    @if ($analysis->armyList->entries->isEmpty())
        <span class="rounded-full bg-zinc-100 px-2.5 py-1 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">Vide</span>
    @else
        @if ($analysis->isValid())
            <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">Conforme</span>
        @else
            <span class="rounded-full bg-red-100 px-2.5 py-1 text-red-800 dark:bg-red-950 dark:text-red-300">{{ count($analysis->violations()) }} problème(s)</span>
        @endif

        @if ($analysis->isPlayable())
            <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">Jouable avec ma collection</span>
        @else
            <span class="rounded-full bg-amber-100 px-2.5 py-1 text-amber-800 dark:bg-amber-950 dark:text-amber-300">{{ $analysis->missingModels() }} figurine(s) manquante(s) ou pas prête(s)</span>
        @endif
    @endif
</div>
