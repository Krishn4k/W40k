{{-- Légende des couleurs de statut de peinture. --}}
<ul class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-zinc-600 dark:text-zinc-400">
    @foreach (App\Enums\PaintStatus::cases() as $status)
        <li class="flex items-center gap-1.5">
            <span class="inline-block size-2.5 rounded-full {{ $status->colorClasses() }}"></span>
            {{ $status->label() }}
        </li>
    @endforeach
</ul>
