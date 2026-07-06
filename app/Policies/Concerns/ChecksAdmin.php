<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait ChecksAdmin
{
    private function admin(User $user): bool
    {
        return $user->isAdmin();
    }
}