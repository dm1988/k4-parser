<?php

namespace App\DTOs;

final readonly class ParsedFlightPlanData
{
    /**
     * @param  array{flight_number?: ?string, trip_number?: ?string, recall_number?: ?string, aircraft_type?: ?string, tail_number?: ?string, flight_date?: ?string, release_revision?: ?string}  $identity
     * @param  array{etd_utc?: ?string, eta_utc?: ?string, block_duration?: ?string, report_time_utc?: ?string, duty_end_utc?: ?string, slot_source_text?: ?string, slots?: list<array{direction: string, airport: string, instant_utc: string, source_time: string, tolerance_minutes?: ?int}>, slot_times_utc?: list<string|null>}  $schedule
     * @param  array{departure?: string, destination?: string, alternate?: ?string, route?: ?string, departure_runway?: ?string, arrival_runway?: ?string, departure_sid?: ?string, arrival_star?: ?string, distance_nautical_miles?: ?int}  $route
     * @param  array{cost_index?: ?int, ramp?: array{amount: float, unit: string}|null, ramp_status?: string, taxi?: array{amount: float, unit: string}|null, takeoff?: array{amount: float, unit: string}|null, takeoff_status?: string, trip?: array{amount: float, unit: string}|null, contingency?: array{amount: float, unit: string}|null, alternate?: array{amount: float, unit: string}|null, final_reserve?: array{amount: float, unit: string}|null, estimated_landing?: array{amount: float, unit: string}|null}  $fuel
     * @param  list<array{name: string, role: ?string, base: ?string, employee_number?: ?string}>  $crewMembers
     * @param  array{section_present?: bool, etops_applicability?: string, items?: list<array{type: string, number: string, description: string, reference: ?string, status: ?string, limitations: ?string, procedures: ?string}>}  $maintenance
     * @param  array{section_present?: bool, source_type?: string, report_reference?: ?string, airport?: ?string, planned_runway?: ?string, outside_air_temperature_celsius?: ?float, wind?: ?string, qnh_inches_mercury?: ?float, qnh_hectopascals?: ?int, maximum_runway_takeoff_weight?: array{amount: int, unit: string}|null, flap_setting?: ?string, anti_ice?: ?bool, v1_knots?: ?int, rotate_knots?: ?int, v2_knots?: ?int, planned_takeoff_weight?: array{amount: int, unit: string}|null, maximum_field_takeoff_weight?: array{amount: int, unit: string}|null, source_warnings?: list<string>}  $envelope
     * @param  array{section_present?: bool, acars_init_date?: ?string, filed_initial_altitude?: ?string, fms_initial_altitude?: ?string}  $flightInit
     * @param  array{etps?: list<array{label: string, airports: string, coordinates: string, scenario: string}>, eent_coordinates?: ?string, eexp_coordinates?: ?string}  $etops
     * @param  array{departure?: array{airport: string, metars: list<string>, tafs: list<string>}|null, destination?: array{airport: string, metars: list<string>, tafs: list<string>}|null, alternate?: array{airport: string, metars: list<string>, tafs: list<string>}|null, raim?: ?string}  $weather
     * @param  array<string, array{amount?: ?int, unit?: string, status?: string}>  $weightBalance
     * @param  array<string, string|list<array{direction: string, airport: string, time: string}>>  $sourceFragments
     * @param  array<string, mixed>  $legacy
     * @param  list<array{coordinate: string, identifier: string, time: ?string, total_time: ?string, remaining_fuel: ?string}>  $waypoints
     */
    public function __construct(
        public array $identity,
        public array $schedule,
        public array $route,
        public array $fuel,
        public array $crewMembers = [],
        public array $maintenance = [],
        public array $envelope = [],
        public array $flightInit = [],
        public array $etops = [],
        public array $weather = [],
        public array $weightBalance = [],
        public array $sourceFragments = [],
        public array $legacy = [],
        public array $waypoints = [],
    ) {}
}
