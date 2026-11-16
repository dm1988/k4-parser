<?php

namespace App\Policies;

use App\Models\CrewBidSnapshot;
use App\Models\User;

class CrewBidSnapshotPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('manage-crew-bids');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CrewBidSnapshot $crewBidSnapshot): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CrewBidSnapshot $crewBidSnapshot): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CrewBidSnapshot $crewBidSnapshot): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CrewBidSnapshot $crewBidSnapshot): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CrewBidSnapshot $crewBidSnapshot): bool
    {
        return false;
    }
}
