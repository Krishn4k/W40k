<?php

namespace App\Policies;

use App\Models\Game;
use App\Models\User;

/**
 * A game is private: only its owner can see or change it.
 */
class GamePolicy
{
    public function view(User $user, Game $game): bool
    {
        return $game->user_id === $user->id;
    }

    public function update(User $user, Game $game): bool
    {
        return $game->user_id === $user->id;
    }

    public function delete(User $user, Game $game): bool
    {
        return $game->user_id === $user->id;
    }
}
