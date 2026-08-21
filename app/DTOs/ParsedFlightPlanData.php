<?php

namespace App\DTOs;

final readonly class ParsedFlightPlanData
{
    /**
     * @param  array{flight_number?: ?string, trip_number?: ?string, recall_number?: ?string, aircraft_type?: ?string, tail_number?: ?string, flight_date?: ?string, release_revision?: ?string}  $identity
     * @param  array{etd_utc?: ?string, eta_utc?: ?string, block_duration?: ?string, report_time_utc?: ?string, duty_end_utc?: ?string, slot_times_utc?: list<string|null>}  $schedule
     * @param  array{departure?: string, destination?: string, alternate?: ?string, route?: ?string, departure_runway?: ?string, arrival_runway?: ?string, departure_sid?: ?string, arrival_star?: ?string, distance_nautical_miles?: ?int}  $route
     * @param  array{ramp?: array{amount: float, unit: string}|null, taxi?: array{amount: float, unit: string}|null, takeoff?: array{amount: float, unit: string}|null, trip?: array{amount: float, unit: string}|null, contingency?: array{amount: float, unit: string}|null, alternate?: array{amount: float, unit: string}|null, final_reserve?: array{amount: float, unit: string}|null, estimated_landing?: array{amount: float, unit: string}|null}  $fuel
     * @param  list<array{name: string, role: ?string, base: ?string}>  $crewMembers
     * @param  array{section_present?: bool, etops_applicability?: string, items?: list<array{type: string, number: string, description: string, reference: ?string, status: ?string, limitations: ?string, procedures: ?string}>}  $maintenance
     * @param  array<string, string|list<array{direction: string, airport: string, time: string}>>  $sourceFragments
     * @param  array<string, mixed>  $legacy
     */
    public function __construct(
        public array $identity,
        public array $schedule,
        public array $route,
        public array $fuel,
        public array $crewMembers = [],
        public array $maintenance = [],
        public array $sourceFragments = [],
        public array $legacy = [],
    ) {}
}
