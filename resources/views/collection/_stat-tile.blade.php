{{-- Tuile de chiffre clé : bandeau de libellé sombre, valeur sur fond d'accent. Attend $label, $value et $detail. --}}
<div class="overflow-hidden rounded-lg shadow-sm">
    <p class="bg-zinc-800 px-4 py-1.5 text-center font-display text-xs font-semibold tracking-widest text-zinc-100 uppercase dark:bg-zinc-950">{{ $label }}</p>
    <div class="bg-accent-600 px-4 py-3 text-center text-white dark:bg-accent-700">
        <p class="font-display text-3xl font-semibold">{{ $value }}</p>
        <p class="text-xs text-accent-50/90">{{ $detail }}</p>
    </div>
</div>
