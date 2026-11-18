<?php

namespace App\Policies;

use App\Models\BidPackage;
use App\Models\User;

class BidPackagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage-crew-bids');
    }

    public function view(User $user, BidPackage $bidPackage): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, BidPackage $bidPackage): bool
    {
        return $this->viewAny($user) && $bidPackage->imported_by === $user->id
            && $bidPackage->published_at === null && ! $bidPackage->workspaces()->exists();
    }

    public function publish(User $user, BidPackage $bidPackage): bool
    {
        return $this->update($user, $bidPackage);
    }

    public function delete(User $user, BidPackage $bidPackage): bool
    {
        return $this->update($user, $bidPackage);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function archive(User $user, BidPackage $bidPackage): bool
    {
        return $this->viewAny($user) && $bidPackage->published_at !== null && $bidPackage->archived_at === null;
    }

    public function restore(User $user, BidPackage $bidPackage): bool
    {
        return $this->viewAny($user) && $bidPackage->published_at !== null && $bidPackage->archived_at !== null;
    }

    public function forceDelete(User $user, BidPackage $bidPackage): bool
    {
        return false;
    }
}
