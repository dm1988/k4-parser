<?php

namespace App\Policies;

use App\Models\Airline;
use App\Models\User;
use App\Policies\Concerns\ChecksAdmin;

class AirlinePolicy
{
    use ChecksAdmin;

    public function viewAny(User $user): bool
    {
        return $this->admin($user);
    }

    public function view(User $user, Airline $airline): bool
    {
        return $this->admin($user);
    }

    public function create(User $user): bool
    {
        return $this->admin($user);
    }

    public function update(User $user, Airline $airline): bool
    {
        return $this->admin($user);
    }

    public function delete(User $user, Airline $airline): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Airline $airline): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, Airline $airline): bool
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