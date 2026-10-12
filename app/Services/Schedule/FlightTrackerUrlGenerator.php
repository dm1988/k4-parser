<?php

namespace App\Services\Schedule;

use App\Enums\FlightTracker;
use App\Enums\MetadataKey;
use App\Enums\ScheduleEventType;
use App\Models\User;
use App\Services\Clients\AirlineCodeLookupClient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class FlightTrackerUrlGenerator
{
    public function __construct(
        private readonly ScheduleAirlineCodes $scheduleAirlineCodes,
        private readonly AirlineCodeLookupClient $airlineCodeLookupClient,
    ) {}

    public function preferredTracker(): FlightTracker
    {
        $user = Auth::user();

        return FlightTracker::fromValue($user instanceof User ? $user->flight_tracker_preference : null);
    }

    public function labelFor(?string $tailNumber, ?string $flightNumber = null): string
    {
        return ($this->preferredTracker() === FlightTracker::Flightradar24 && $this->flightradar24Url($tailNumber, $flightNumber) !== null
            ? FlightTracker::Flightradar24
            : FlightTracker::FlightAware)->label();
    }

    public function url(?string $tailNumber, ?string $flightNumber = null, ?FlightTracker $tracker = null): ?string
    {
        $tracker ??= $this->preferredTracker();

        if ($tracker === FlightTracker::Flightradar24) {
            $url = $this->flightradar24Url($tailNumber, $flightNumber);

            if ($url !== null) {
                return $url;
            }
        }

        return $this->flightAwareUrl($tailNumber, $flightNumber);
    }

    public function flightAwareUrl(?string $tailNumber, ?string $flightNumber = null): ?string
    {
        $identifier = $this->tailIdentifier($tailNumber) ?? $this->flightIdentifier($flightNumber, false);

        return $identifier === null ? null : rtrim((string) config('services.flightaware.url'), '/').'/'.rawurlencode($identifier);
    }

    public function flightradar24Url(?string $tailNumber, ?string $flightNumber = null): ?string
    {
        $tail = $this->tailIdentifier($tailNumber);
        $identifier = $tail ?? $this->flightIdentifier($flightNumber, true);

        return $identifier === null ? null : rtrim((string) config('services.flightradar24.url'), '/')
            .($tail !== null ? '/aircraft/' : '/flights/').rawurlencode(Str::lower($identifier));
    }

    public function forEvent(array $event): ?string
    {
        if (! ScheduleEventType::isFlightEvent($event)) {
            return null;
        }

        return $this->url(
            data_get($event, 'metadata.'.MetadataKey::TailNumber->value) ?? $event['tailNumber'] ?? $event['tail_number'] ?? null,
            data_get($event, 'metadata.'.MetadataKey::FlightNumber->value) ?? $event['flightNumber'] ?? $event['flight_number'] ?? null,
        );
    }

    private function tailIdentifier(?string $value): ?string
    {
        $value = Str::upper(trim((string) $value));

        return preg_match('/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/', $value) === 1 ? $value : null;
    }

    private function flightIdentifier(?string $value, bool $useIata): ?string
    {
        $value = Str::upper(preg_replace('/\s+/', '', (string) $value) ?? '');

        if (preg_match('/^([A-Z]{3}|[A-Z][A-Z0-9]|[0-9][A-Z])?(\d{1,5}[A-Z]?)$/', $value, $matches) !== 1) {
            return null;
        }

        $prefix = $matches[1];

        if ($prefix === '' || $prefix === $this->scheduleAirlineCodes->iata() || $prefix === $this->scheduleAirlineCodes->icao()) {
            $prefix = $useIata ? $this->scheduleAirlineCodes->iata() : $this->scheduleAirlineCodes->icao();
        } elseif ($useIata && strlen($prefix) === 3) {
            $prefix = $this->airlineCodeLookupClient->iataCodeForIcaoCode($prefix);
        } elseif (! $useIata && strlen($prefix) === 2) {
            $prefix = $this->airlineCodeLookupClient->icaoCodeForIataCode($prefix) ?? $prefix;
        }

        return $prefix === null || $prefix === '' ? null : $prefix.$matches[2];
    }
}
