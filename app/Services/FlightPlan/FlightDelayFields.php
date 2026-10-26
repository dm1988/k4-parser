<?php

namespace App\Services\FlightPlan;

use App\Models\FlightDelay;
use Carbon\CarbonImmutable;

class FlightDelayFields
{
    public const array EVENTS = [
        'logbook_received_at' => 'Logbook received',
        'flight_following_brief_at' => 'Flight following brief',
        'fueling_completed_at' => 'Fueling completed',
        'loading_completed_at' => 'Loading completed',
        'load_master_briefing_at' => 'Load master briefing',
        'new_payload_received_at' => 'New payload received',
        'new_recall_flight_plan_at' => 'New recall / flight plan',
        'new_tlr_at' => 'New TLR',
        'other_at' => 'Other',
    ];

    public const array DURATION = [
        'delay_started_at' => 'Delay started',
        'delay_ended_at' => 'Delay ended',
    ];

    public const array TEXT = [
        'other_label' => 'Other event label',
        'other_details' => 'Other event details',
        'delay_cause' => 'Delay cause',
        'delay_timeline' => 'Delay timeline',
        'actions_taken' => 'Actions taken',
        'notes' => 'Notes',
        'additional_facts' => 'Additional facts to include in report',
    ];

    /** @return array<string, string> */
    public static function timestamps(): array
    {
        return [...self::EVENTS, ...self::DURATION];
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [...self::timestamps(), ...self::TEXT];
    }

    /** @return array<string, string> */
    public static function inputs(?FlightDelay $delay = null): array
    {
        $inputs = [];

        foreach (self::labels() as $field => $label) {
            $value = $delay?->getAttribute($field);
            $inputs[$field] = $value instanceof CarbonImmutable
                ? $value->utc()->format('Y-m-d\TH:i')
                : (is_string($value) ? $value : '');
        }

        return $inputs;
    }

    /**
     * @param  array<string, string|null>  $inputs
     * @return array<string, CarbonImmutable|string|null>
     */
    public static function normalize(array $inputs): array
    {
        $normalized = [];

        foreach ($inputs as $field => $value) {
            $normalized[$field] = $value === null || trim($value) === ''
                ? null
                : (isset(self::timestamps()[$field])
                    ? CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $value, 'UTC')
                    : $value);
        }

        return $normalized;
    }

    /** @param array<string, mixed> $payload
     * @return array<string, string|null>
     */
    public static function flightContext(array $payload): array
    {
        $data = $payload['flight_plan_data'] ?? [];
        $values = [
            'flight_number' => $data['identity']['flightNumber'] ?? null,
            'flight_date' => $data['identity']['flightDate'] ?? null,
            'tail_number' => $data['identity']['tailNumber'] ?? null,
            'departure' => $data['route']['departure'] ?? null,
            'destination' => $data['route']['destination'] ?? null,
            'scheduled_departure_utc' => $data['schedule']['etdUtc'] ?? null,
        ];

        return array_map(static fn (mixed $value): ?string => is_string($value) && $value !== '' ? $value : null, $values);
    }
}
