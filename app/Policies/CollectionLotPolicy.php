<?php

namespace App\Policies;

use App\Models\CollectionLot;
use App\Models\User;

/**
 * A collection lot is private: only its owner can see or change it.
 */
class CollectionLotPolicy
{
    public function view(User $user, CollectionLot $collectionLot): bool
    {
        return $collectionLot->user_id === $user->id;
    }

    public function update(User $user, CollectionLot $collectionLot): bool
    {
        return $collectionLot->user_id === $user->id;
    }

    public function delete(User $user, CollectionLot $collectionLot): bool
    {
        return $collectionLot->user_id === $user->id;
    }
}
