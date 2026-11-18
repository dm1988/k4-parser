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
        public ?SubmittedBidOrder $submittedOrder = null,
    ) {}

    public function withSubmittedOrder(SubmittedBidOrder $order): self
    {
        return new self(
            seat: $this->seat,
            preferences: $this->preferences,
            selections: $this->selections,
            tab: $this->tab,
            sort: $this->sort,
            minimumCredit: $this->minimumCredit,
            minimumDaysOff: $this->minimumDaysOff,
            selectedOnly: $this->selectedOnly,
            layoverCity: $this->layoverCity,
            extendedLayoversOnly: $this->extendedLayoversOnly,
            avoidShortLayovers: $this->avoidShortLayovers,
            submittedOrder: $order,
        );
    }

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
