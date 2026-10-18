{{-- Barre d'avancement à trois segments : validé, à vérifier, à traduire. Attend $counts (total, missing, review, validated). --}}
@php($total = max(1, $counts['total']))
<div class="flex h-2 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800"
     role="img" aria-label="{{ $counts['validated'] }} validé(s), {{ $counts['review'] }} à vérifier, {{ $counts['missing'] }} à traduire">
    <div class="bg-emerald-600" style="width: {{ round($counts['validated'] / $total * 100, 2) }}%"></div>
    <div class="bg-amber-500" style="width: {{ round($counts['review'] / $total * 100, 2) }}%"></div>
    <div class="bg-red-400" style="width: {{ round($counts['missing'] / $total * 100, 2) }}%"></div>
</div>
