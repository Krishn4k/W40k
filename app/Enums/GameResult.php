<?php

namespace App\Enums;

enum GameResult: string
{
    case Win = 'win';
    case Draw = 'draw';
    case Loss = 'loss';

    /**
     * French label displayed in the interface.
     */
    public function label(): string
    {
        return match ($this) {
            self::Win => 'Victoire',
            self::Draw => 'Égalité',
            self::Loss => 'Défaite',
        };
    }

    /**
     * Tailwind classes of the result badge.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Win => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
            self::Draw => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
            self::Loss => 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
        };
    }
}
