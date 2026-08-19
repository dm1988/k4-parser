<?php

namespace App\Actions;

use App\Models\User;

class ShouldPromptForCoffee
{
    public function handle(User $user): bool
    {
        if ($user->has_bought_coffee) {
            return false;
        }

        $qualifyingExtractionCount = $user->extractRequests()
            ->where('status', 'success')
            ->where('detected_event_count', '>', 0)
            ->count();

        return $qualifyingExtractionCount > 7 && $qualifyingExtractionCount % 2 === 0;
    }
}
