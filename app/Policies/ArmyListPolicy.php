<?php

namespace App\Policies;

use App\Models\ArmyList;
use App\Models\User;

/**
 * An army list is private: only its owner can see or change it.
 */
class ArmyListPolicy
{
    public function view(User $user, ArmyList $armyList): bool
    {
        return $armyList->user_id === $user->id;
    }

    public function update(User $user, ArmyList $armyList): bool
    {
        return $armyList->user_id === $user->id;
    }

    public function delete(User $user, ArmyList $armyList): bool
    {
        return $armyList->user_id === $user->id;
    }
}
