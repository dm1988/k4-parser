<?php

namespace App\Policies;

use App\Models\ExtractRequest;
use App\Models\User;
use App\Policies\Concerns\ChecksAdmin;

class ExtractRequestPolicy
{
    use ChecksAdmin;

    public function viewAny(User $user): bool
    {
        return $this->admin($user);
    }

    public function view(User $user, ExtractRequest $extractRequest): bool
    {
        return $this->admin($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ExtractRequest $extractRequest): bool
    {
        return false;
    }

    public function delete(User $user, ExtractRequest $extractRequest): bool
    {
        return $this->admin($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->admin($user);
    }

    public function restore(User $user, ExtractRequest $extractRequest): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, ExtractRequest $extractRequest): bool
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
