<?php

namespace App\Enums;

enum PaintStatus: string
{
    case OnSprue = 'on_sprue';
    case Assembled = 'assembled';
    case Primed = 'primed';
    case Painted = 'painted';
    case Finished = 'finished';

    /**
     * French label displayed in the interface.
     */
    public function label(): string
    {
        return match ($this) {
            self::OnSprue => 'Sur grappe',
            self::Assembled => 'Monté',
            self::Primed => 'Sous-couché',
            self::Painted => 'Peint',
            self::Finished => 'Terminé',
        };
    }

    /**
     * Tailwind background classes used by progress bars and legends.
     */
    public function colorClasses(): string
    {
        return match ($this) {
            self::OnSprue => 'bg-zinc-300 dark:bg-zinc-700',
            self::Assembled => 'bg-zinc-500',
            self::Primed => 'bg-sky-600',
            self::Painted => 'bg-amber-500',
            self::Finished => 'bg-emerald-600',
        };
    }

    /**
     * Position in the painting progression, from 0 (on sprue) to 4 (finished).
     */
    public function rank(): int
    {
        return array_search($this, self::cases(), true);
    }

    /**
     * Whether this status has reached the given threshold (e.g. the playable minimum status).
     */
    public function isAtLeast(self $threshold): bool
    {
        return $this->rank() >= $threshold->rank();
    }
}
