<?php

namespace App\Policies;

use App\Models\PilotSeniorityList;
use App\Models\User;

class PilotSeniorityListPolicy
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
    public function view(User $user, PilotSeniorityList $pilotSeniorityList): bool
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
    public function update(User $user, PilotSeniorityList $pilotSeniorityList): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PilotSeniorityList $pilotSeniorityList): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, PilotSeniorityList $pilotSeniorityList): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, PilotSeniorityList $pilotSeniorityList): bool
    {
        return false;
    }
}
