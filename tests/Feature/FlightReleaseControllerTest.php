<?php

namespace Tests\Feature;

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
        Storage::fake('local');

        $this->mock(FlightRouteExtractor::class, function (MockInterface $mock): void {
            $mock->shouldReceive('extractRoute')
                ->once()
                ->andReturn('OSUDO4A ASETA UZ152 UKLEN UL310 ARULA UM400 CBA UZ105 UMKAL UMKAL6A');
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

        $this->get(route('flight-release.index'))
            ->assertOk()
            ->assertSeeText('Copy route')
            ->assertSee('OSUDO4A ASETA UZ152 UKLEN UL310 ARULA UM400 CBA UZ105', escape: false)
            ->assertSee(' UMKAL UMKAL6A', escape: false);
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
        Storage::fake('local');
        Log::spy();

        $this->mock(FlightRouteExtractor::class, function (MockInterface $mock): void {
            $mock->shouldReceive('extractRoute')
                ->once()
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

        Log::shouldHaveReceived('warning')->once();
    }
}
