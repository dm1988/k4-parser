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

        $promptAfterExtractions = (int) config('services.buy_me_a_coffee.prompt_after_extractions', 7);
        $promptInterval = (int) config('services.buy_me_a_coffee.prompt_interval', 2);

        if ($promptInterval <= 0) {
            return false;
        }

        return $qualifyingExtractionCount > $promptAfterExtractions
            && $qualifyingExtractionCount % $promptInterval === 0;
    }
}
