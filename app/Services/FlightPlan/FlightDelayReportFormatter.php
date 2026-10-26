<?php

namespace App\Services\FlightPlan;

use App\Models\FlightDelay;

class FlightDelayReportFormatter
{
    /** @return array<string, string> */
    public function context(FlightDelay $delay): array
    {
        $snapshot = $delay->flight_context ?? [];
        $fields = [
            'Flight' => 'flight_number',
            'Flight date' => 'flight_date',
            'Tail' => 'tail_number',
            'Departure' => 'departure',
            'Destination' => 'destination',
            'Scheduled departure (UTC)' => 'scheduled_departure_utc',
        ];
        $details = [];

        foreach ($fields as $label => $field) {
            $value = $snapshot[$field] ?? null;
            $details[$label] = is_string($value) && $value !== '' ? $value : 'Not recorded';
        }

        return $details;
    }

    public function format(FlightDelay $delay): string
    {
        $lines = ['Flight delay report', 'All times UTC'];

        foreach ($this->context($delay) as $label => $value) {
            $lines[] = $label.': '.$value;
        }

        $events = [];

        foreach (FlightDelayFields::EVENTS as $field => $label) {
            $time = $delay->getAttribute($field);

            if ($time !== null) {
                $eventLabel = $field === 'other_at' && $delay->other_label !== null
                    ? 'Other: '.$delay->other_label
                    : $label;
                $events[] = ['time' => $time->utc()->format('Y-m-d H:i'), 'order' => count($events), 'label' => $eventLabel];
            }
        }

        usort($events, static fn (array $left, array $right): int => [$left['time'], $left['order']] <=> [$right['time'], $right['order']]);
        $lines[] = '';
        $lines[] = 'Recorded event timeline:';

        if ($events === []) {
            $lines[] = 'Not recorded';
        }

        foreach ($events as $event) {
            $lines[] = $event['time'].' UTC — '.$event['label'];
        }

        $lines[] = '';

        foreach (FlightDelayFields::DURATION as $field => $label) {
            $lines[] = $label.': '.($delay->getAttribute($field)?->utc()->format('Y-m-d H:i').' UTC');

            if ($delay->getAttribute($field) === null) {
                $lines[array_key_last($lines)] = $label.': Not recorded';
            }
        }

        $minutes = $delay->delay_started_at !== null && $delay->delay_ended_at !== null
            ? (int) $delay->delay_started_at->diffInMinutes($delay->delay_ended_at)
            : null;
        $lines[] = 'Delay duration: '.($minutes === null
            ? 'Not available — both delay endpoints are required'
            : $minutes.' min (entered delay start/end interval)');

        foreach (FlightDelayFields::TEXT as $field => $label) {
            $value = $delay->getAttribute($field);

            if (is_string($value) && trim($value) !== '') {
                $lines[] = '';
                $lines[] = $label.':';
                $lines[] = $value;
            }
        }

        return implode("\n", $lines);
    }
}
