<?php

namespace App\Policies;

use App\Models\ParseRequest;
use App\Models\User;
use App\Policies\Concerns\ChecksAdmin;

class ParseRequestPolicy
{
    use ChecksAdmin;

    public function viewAny(User $user): bool
    {
        return $this->admin($user);
    }

    public function view(User $user, ParseRequest $parseRequest): bool
    {
        return $this->admin($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ParseRequest $parseRequest): bool
    {
        return false;
    }

    public function delete(User $user, ParseRequest $parseRequest): bool
    {
        return $this->admin($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->admin($user);
    }

    public function restore(User $user, ParseRequest $parseRequest): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, ParseRequest $parseRequest): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }
}