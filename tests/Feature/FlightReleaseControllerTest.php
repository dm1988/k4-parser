<?php

namespace Tests\Feature;

use App\DTOs\AirportData;
use App\Exceptions\FlightRouteNotFoundException;
use App\Models\User;
use App\Services\FlightRouteExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class FlightReleaseControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_the_flight_release_extractor_page(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('flight-release.index'));

        $response->assertOk();
        $response->assertSeeText('Flight Release Route Extractor');
    }

    public function test_uploaded_pdf_route_is_displayed_after_extraction(): void
    {
        Storage::fake('user_flight_releases');

        $this->mock(FlightRouteExtractor::class, function (MockInterface $mock): void {
            $mock->shouldReceive('extractFlightPlanData')
                ->once()
                ->withArgs(function (string $path): bool {
                    return str_contains($path, 'framework/testing/disks/user_flight_releases');
                })
                ->andReturn([
                    'departure' => 'PANC',
                    'destination' => 'KMIA',
                    'alternate' => 'KRSW',
                    'departure_airport' => new AirportData('PANC', 'ANC', 'Ted Stevens Anchorage International Airport', 'Anchorage', 'Alaska', 'United States'),
                    'destination_airport' => new AirportData('KMIA', 'MIA', 'Miami International Airport', 'Miami', 'Florida', 'United States'),
                    'alternate_airport' => new AirportData('KRSW', 'RSW', 'Southwest Florida International Airport', 'Fort Myers', 'Florida', 'United States'),
                    'initial_altitude' => 'FL 330',
                    'duration' => '07h12m',
                    'route' => 'OSUDO4A ASETA UZ152 UKLEN UL310 ARULA UM400 CBA UZ105 UMKAL UMKAL6A',
                ]);
            $mock->shouldReceive('formatForIcaoDisplay')
                ->once()
                ->with('OSUDO4A ASETA UZ152 UKLEN UL310 ARULA UM400 CBA UZ105 UMKAL UMKAL6A')
                ->andReturn("OSUDO4A ASETA UZ152 UKLEN UL310 ARULA UM400 CBA UZ105\n UMKAL UMKAL6A");
        });

        $response = $this->actingAs(User::factory()->create())
            ->from(route('flight-release.index'))
            ->post(route('flight-release.store'), [
                'flight_release' => UploadedFile::fake()->create('flight-release.pdf', 120, 'application/pdf'),
            ]);

        $response->assertRedirect(route('flight-release.index'));
        $this->assertSame([], Storage::disk('user_flight_releases')->allFiles());

        $this->get(route('flight-release.index'))
            ->assertOk()
            ->assertSeeText('Extracted flight plan')
            ->assertSeeText('Departure')
            ->assertSeeText('PANC')
            ->assertSeeText('Destination')
            ->assertSeeText('KMIA')
            ->assertSeeText('Miami International Airport')
            ->assertSeeText('Alternate')
            ->assertSeeText('KRSW')
            ->assertSeeText('Southwest Florida International Airport')
            ->assertSeeText('FL 330')
            ->assertSeeText('07h12m')
            ->assertSeeText('Airport details')
            ->assertSeeText('Route')
            ->assertSeeText('Copy route')
            ->assertSee('OSUDO4A ASETA UZ152 UKLEN UL310 ARULA UM400 CBA UZ105', escape: false)
            ->assertSee(' UMKAL UMKAL6A', escape: false);
    }

    public function test_uploaded_pdf_route_page_handles_missing_airport_details(): void
    {
        Storage::fake('user_flight_releases');

        $this->mock(FlightRouteExtractor::class, function (MockInterface $mock): void {
            $mock->shouldReceive('extractFlightPlanData')
                ->once()
                ->withArgs(function (string $path): bool {
                    return str_contains($path, 'framework/testing/disks/user_flight_releases');
                })
                ->andReturn([
                    'departure' => 'PANC',
                    'destination' => 'KMIA',
                    'alternate' => null,
                    'departure_airport' => null,
                    'destination_airport' => null,
                    'alternate_airport' => null,
                    'initial_altitude' => 'FL 330',
                    'duration' => '07h12m',
                    'route' => 'DCT TEST',
                ]);
            $mock->shouldReceive('formatForIcaoDisplay')
                ->once()
                ->with('DCT TEST')
                ->andReturn('DCT TEST');
        });

        $response = $this->actingAs(User::factory()->create())
            ->from(route('flight-release.index'))
            ->post(route('flight-release.store'), [
                'flight_release' => UploadedFile::fake()->create('flight-release.pdf', 120, 'application/pdf'),
            ]);

        $response->assertRedirect(route('flight-release.index'));

        $this->get(route('flight-release.index'))
            ->assertOk()
            ->assertSeeText('Airport details unavailable.')
            ->assertSeeText('No alternate airport listed.');
    }

    public function test_flight_release_page_displays_array_backed_airport_details_from_session(): void
    {
        $flightPlan = [
            'departure' => 'PANC',
            'destination' => 'KMIA',
            'alternate' => 'KRSW',
            'departure_airport' => [
                'icao' => 'PANC',
                'iata' => 'ANC',
                'name' => 'Ted Stevens Anchorage International Airport',
                'city' => 'Anchorage',
                'state' => 'Alaska',
                'country' => 'United States',
            ],
            'destination_airport' => [
                'icao' => 'KMIA',
                'iata' => 'MIA',
                'name' => 'Miami International Airport',
                'city' => 'Miami',
                'state' => 'Florida',
                'country' => 'United States',
            ],
            'alternate_airport' => [
                'icao' => 'KRSW',
                'iata' => 'RSW',
                'name' => 'Southwest Florida International Airport',
                'city' => 'Fort Myers',
                'state' => 'Florida',
                'country' => 'United States',
            ],
            'initial_altitude' => 'FL 330',
            'duration' => '07h12m',
            'route' => 'DCT TEST',
        ];

        $this->actingAs(User::factory()->create())
            ->withSession(['flight_plan' => $flightPlan])
            ->get(route('flight-release.index'))
            ->assertOk()
            ->assertSeeText('Ted Stevens Anchorage International Airport')
            ->assertSeeText('Miami International Airport')
            ->assertSeeText('Southwest Florida International Airport');
    }

    public function test_only_pdf_uploads_are_allowed(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->from(route('flight-release.index'))
            ->post(route('flight-release.store'), [
                'flight_release' => UploadedFile::fake()->create('flight-release.txt', 8, 'text/plain'),
            ]);

        $response->assertRedirect(route('flight-release.index'));
        $response->assertSessionHasErrors([
            'flight_release' => 'Only PDF flight release uploads are supported.',
        ]);
    }

    public function test_route_not_found_error_is_returned_when_extractor_cannot_match_route(): void
    {
        Storage::fake('user_flight_releases');
        Log::spy();

        $this->mock(FlightRouteExtractor::class, function (MockInterface $mock): void {
            $mock->shouldReceive('extractFlightPlanData')
                ->once()
                ->withArgs(function (string $path): bool {
                    return str_contains($path, 'framework/testing/disks/user_flight_releases');
                })
                ->andThrow(FlightRouteNotFoundException::routeSegmentMissing());
        });

        $response = $this->actingAs(User::factory()->create())
            ->from(route('flight-release.index'))
            ->post(route('flight-release.store'), [
                'flight_release' => UploadedFile::fake()->create('flight-release.pdf', 120, 'application/pdf'),
            ]);

        $response->assertRedirect(route('flight-release.index'));
        $response->assertSessionHasErrors([
            'flight_release' => 'A flight plan block was found, but the route segment could not be identified between the speed/level and destination lines.',
        ]);

        $this->assertSame([], Storage::disk('user_flight_releases')->allFiles());
        Log::shouldHaveReceived('warning')->once();
    }
}
