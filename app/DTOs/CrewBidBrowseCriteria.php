<?php

namespace App\DTOs;

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
        public string $sort = 'best',
        public ?int $minimumCredit = null,
        public ?int $minimumDaysOff = null,
        public bool $selectedOnly = false,
        public string $layoverCity = '',
        public bool $extendedLayoversOnly = false,
        public bool $avoidShortLayovers = false,
    ) {}
}
