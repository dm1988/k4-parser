<?php

namespace App\Policies;

use App\Models\Aircraft;
use App\Models\User;
use App\Policies\Concerns\ChecksAdmin;

class AircraftPolicy
{
    use ChecksAdmin;

    public function viewAny(User $user): bool
    {
        return $this->admin($user);
    }

    public function view(User $user, Aircraft $aircraft): bool
    {
        return $this->admin($user);
    }

    public function create(User $user): bool
    {
        return $this->admin($user);
    }

    public function update(User $user, Aircraft $aircraft): bool
    {
        return $this->admin($user);
    }

    public function delete(User $user, Aircraft $aircraft): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Aircraft $aircraft): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, Aircraft $aircraft): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return $this->admin($user);
    }
}