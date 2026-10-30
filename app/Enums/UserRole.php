<?php

namespace App\Enums;

/**
 * Account role: players use the app, translators also edit the reference data and its translations,
 * administrators also manage accounts, registrations and maintenance.
 */
enum UserRole: string
{
    case Player = 'player';
    case Translator = 'translator';
    case Admin = 'admin';

    /**
     * French label displayed in the interface.
     */
    public function label(): string
    {
        return match ($this) {
            self::Player => 'Joueur',
            self::Translator => 'Traducteur',
            self::Admin => 'Administrateur',
        };
    }

    /**
     * Whether the role may edit the reference data and its translations.
     */
    public function canEditReference(): bool
    {
        return $this !== self::Player;
    }
}
