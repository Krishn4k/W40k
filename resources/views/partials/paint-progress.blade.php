{{-- Barre de progression empilée par statut de peinture. Attend $counts : [valeur du statut => nombre]. --}}
@php($total = array_sum($counts))
<div class="flex h-2.5 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800"
     role="img"
     aria-label="{{ collect(App\Enums\PaintStatus::cases())->filter(fn ($status) => ($counts[$status->value] ?? 0) > 0)->map(fn ($status) => $status->label().' : '.$counts[$status->value])->join(', ') }}">
    @if ($total > 0)
        @foreach (App\Enums\PaintStatus::cases() as $status)
            @if (($counts[$status->value] ?? 0) > 0)
                <div class="{{ $status->colorClasses() }}" style="width: {{ round($counts[$status->value] / $total * 100, 2) }}%"></div>
            @endif
        @endforeach
    @endif
</div>
