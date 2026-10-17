<?php

namespace App\Enums;

enum UnitRole: string
{
    case Character = 'character';
    case Battleline = 'battleline';
    case DedicatedTransport = 'dedicated_transport';
    case Fortification = 'fortification';
    case Other = 'other';

    /**
     * French label displayed in the interface.
     */
    public function label(): string
    {
        return match ($this) {
            self::Character => 'Personnage',
            self::Battleline => 'Ligne',
            self::DedicatedTransport => 'Transport assigné',
            self::Fortification => 'Fortification',
            self::Other => 'Autre',
        };
    }
}
