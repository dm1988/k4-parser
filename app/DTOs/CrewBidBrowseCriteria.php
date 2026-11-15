<?php

namespace App\DTOs;

use App\Enums\CrewBidGoal;
use App\Enums\CrewBidSort;

final readonly class CrewBidBrowseCriteria
{
    /**
     * @param  array{goals: list<string>, days_off: list<string>, start_city: string, initialized: bool}  $preferences
     * @param  list<string>  $selections
     */
    public function __construct(
        public string $seat,
        public array $preferences,
        public array $selections = [],
        public string $tab = 'flying',
        public CrewBidSort $sort = CrewBidSort::BestMatch,
        public ?int $minimumCredit = null,
        public ?int $minimumDaysOff = null,
        public bool $selectedOnly = false,
        public string $layoverCity = '',
        public bool $extendedLayoversOnly = false,
        public bool $avoidShortLayovers = false,
    ) {}

    /** @return list<CrewBidGoal> */
    public function rankingGoals(): array
    {
        $goals = [];
        foreach ($this->preferences['goals'] as $value) {
            $goal = CrewBidGoal::tryFrom($value);
            if ($goal !== null) {
                $goals[] = $goal;
            }
        }

        return $goals;
    }
}
