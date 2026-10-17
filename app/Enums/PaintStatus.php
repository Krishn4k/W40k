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
