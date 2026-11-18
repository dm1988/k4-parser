<?php

namespace App\DTOs;

use App\View\Models\Extract\FlightCardViewModel;

final readonly class BidAwardsPageData
{
    /**
     * @param  list<array{name: string, seat: string, isUser: bool}>  $crew
     * @param  list<array{date: string, day: string, weekday: string, label: string, transition: bool, status: string, activity: string}>  $days
     * @param  list<FlightCardViewModel>  $flights
     * @param  list<DutyEvent>  $duties
     * @param  list<string>  $warnings
     */
    public function __construct(
        public bool $employeeRequired,
        public bool $matched,
        public array $crew,
        public array $days,
        public array $flights,
        public array $duties,
        public ?string $lineLabel,
        public array $warnings,
        public ExtractedResultData $result,
        public int $calendarOffset = 0,
    ) {}
}
