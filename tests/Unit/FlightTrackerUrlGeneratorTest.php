<?php

namespace Tests\Unit;

use App\Enums\FlightTracker;
use App\Models\User;
use App\Services\Schedule\FlightTrackerUrlGenerator;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FlightTrackerUrlGeneratorTest extends TestCase
{
    #[DataProvider('identifiers')]
    public function test_it_generates_normalized_urls(?string $tail, ?string $flight, FlightTracker $tracker, ?string $expected): void
    {
        DB::shouldReceive('connection')->never();
        $this->assertSame($expected, app(FlightTrackerUrlGenerator::class)->url($tail, $flight, $tracker));
    }

    public static function identifiers(): array
    {
        return [
            [' n772ck ', 'CKS 240', FlightTracker::FlightAware, 'https://www.flightaware.com/live/flight/N772CK'],
            ['N772CK', 'CKS 240', FlightTracker::Flightradar24, 'https://www.flightradar24.com/data/aircraft/n772ck'],
            ['D-ABCD', null, FlightTracker::Flightradar24, 'https://www.flightradar24.com/data/aircraft/d-abcd'],
            [null, ' cks 240 ', FlightTracker::Flightradar24, 'https://www.flightradar24.com/data/flights/k4240'],
            [null, 'K4 240', FlightTracker::FlightAware, 'https://www.flightaware.com/live/flight/CKS240'],
            [null, '240', FlightTracker::Flightradar24, 'https://www.flightradar24.com/data/flights/k4240'],
            [null, 'UA 5445', FlightTracker::FlightAware, 'https://www.flightaware.com/live/flight/UAL5445'],
            [null, 'UAL 5445', FlightTracker::Flightradar24, 'https://www.flightradar24.com/data/flights/ua5445'],
            [null, 'G4 368', FlightTracker::FlightAware, 'https://www.flightaware.com/live/flight/AAY368'],
            [null, 'CX 413', FlightTracker::Flightradar24, 'https://www.flightradar24.com/data/flights/cx413'],
            [null, 'ZZQ 123', FlightTracker::Flightradar24, 'https://www.flightaware.com/live/flight/ZZQ123'],
            ['../unsafe', 'UA 123', FlightTracker::Flightradar24, 'https://www.flightradar24.com/data/flights/ua123'],
            [null, null, FlightTracker::FlightAware, null],
            ['', '', FlightTracker::Flightradar24, null],
            ['https://example.test', 'javascript:alert(1)', FlightTracker::FlightAware, null],
            [null, 'R2', FlightTracker::FlightAware, null],
        ];
    }

    public function test_current_preference_and_invalid_values_are_resolved_on_every_call(): void
    {
        $generator = app(FlightTrackerUrlGenerator::class);
        $user = User::factory()->make(['flight_tracker_preference' => 'flightradar24']);
        $this->actingAs($user);
        $this->assertSame('https://www.flightradar24.com/data/aircraft/n772ck', $generator->url('N772CK'));
        $this->assertSame('Flightradar24', $generator->labelFor('N772CK'));

        $user->flight_tracker_preference = 'invalid';
        $this->assertSame('https://www.flightaware.com/live/flight/N772CK', $generator->url('N772CK'));
        $this->assertSame('FlightAware', $generator->labelFor('N772CK'));

        $this->assertSame(FlightTracker::FlightAware, FlightTracker::fromValue(null));
        $this->actingAsGuest();
        $this->assertSame(FlightTracker::FlightAware, $generator->preferredTracker());
    }

    public function test_it_uses_configured_base_urls_and_custom_company_codes(): void
    {
        config(['services.flightaware.url' => 'https://tracker.test/live/', 'services.flightradar24.url' => 'https://radar.test/data/']);
        $this->actingAs(User::factory()->make(['airline_iata_code' => 'AB', 'airline_icao_code' => 'XYZ']));
        $generator = app(FlightTrackerUrlGenerator::class);

        $this->assertSame('https://tracker.test/live/XYZ123', $generator->flightAwareUrl(null, 'AB 123'));
        $this->assertSame('https://radar.test/data/flights/ab123', $generator->flightradar24Url(null, 'XYZ 123'));
        $this->assertSame('https://radar.test/data/aircraft/n772ck', $generator->flightradar24Url('N772CK'));
    }

    public function test_missing_identifiers_and_non_flight_events_do_not_use_cached_urls(): void
    {
        $generator = app(FlightTrackerUrlGenerator::class);
        $this->assertNull($generator->forEvent(['type' => 'flight', 'metadata' => ['flightaware_url' => 'https://example.test/export']]));
        $this->assertNull($generator->forEvent(['type' => 'duty', 'metadata' => ['duty_type' => 'R2', 'flight_number' => 'R2']]));
        $this->assertNull($generator->forEvent(['type' => 'layover', 'metadata' => ['tail_number' => 'N772CK']]));
    }

    public function test_unknown_icao_fallback_has_the_correct_provider_label(): void
    {
        $this->actingAs(User::factory()->make(['flight_tracker_preference' => 'flightradar24']));
        $this->assertSame('FlightAware', app(FlightTrackerUrlGenerator::class)->labelFor(null, 'ZZQ 123'));
    }
}
