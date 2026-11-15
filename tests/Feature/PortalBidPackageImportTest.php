<?php

namespace Tests\Feature;

use App\Actions\CrewBids\ImportPortalBidPackage;
use App\Models\BidPackage;
use App\Models\User;
use App\Services\CrewBids\DownloadPortalBidPackage;
use App\Services\CrewBids\ParseBidDocuments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class PortalBidPackageImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_downloads_only_the_three_expected_public_documents(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            '*/777%20December%202026%20Lines.pdf' => Http::response('%PDF-lines', 200, ['Content-Type' => 'application/pdf']),
            '*/777%20December%202026%20Trips.pdf' => Http::response('%PDF-trips', 200, ['Content-Type' => 'application/pdf']),
            '*/777%20December%202026%20Overview.xlsx' => Http::response("PK\x03\x04overview", 200, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']),
        ]);

        $files = app(DownloadPortalBidPackage::class)->download('2026-12', '777');

        $this->assertSame(['777 December 2026 Lines.pdf', '777 December 2026 Trips.pdf', '777 December 2026 Overview.xlsx'], array_map(fn (UploadedFile $file): string => $file->getClientOriginalName(), $files));
        $this->assertSame('%PDF-lines', file_get_contents($files[0]->getPathname()));
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://crewbids.kalittaair.com/hyperlinks/777%20December%202026%20Overview.xlsx');
        app(DownloadPortalBidPackage::class)->cleanup($files);
    }

    public function test_missing_portal_file_is_not_imported(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*/777%20December%202026%20Lines.pdf' => Http::response('', 404)]);

        $this->assertNull(app(DownloadPortalBidPackage::class)->download('2026-12', '777'));
    }

    public function test_html_portal_response_is_rejected(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*/777%20December%202026%20Lines.pdf' => Http::response('<html>Login</html>', 200, ['Content-Type' => 'text/html'])]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('invalid or oversized bid document');
        app(DownloadPortalBidPackage::class)->download('2026-12', '777');
    }

    public function test_login_redirect_is_rejected_without_following_it(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*/777%20December%202026%20Lines.pdf' => Http::response('', 302, ['Location' => '/login.aspx'])]);

        try {
            app(DownloadPortalBidPackage::class)->download('2026-12', '777');
            $this->fail('A login redirect must not be imported.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('invalid', $exception->getMessage());
        }

        Http::assertSentCount(1);
    }

    public function test_first_complete_package_publishes_duplicate_is_unchanged_and_revision_is_held(): void
    {
        $owner = User::factory()->admin()->create();
        $files = [UploadedFile::fake()->create('Lines.pdf', 1, 'application/pdf')];
        $downloader = $this->mock(DownloadPortalBidPackage::class);
        $downloader->shouldReceive('download')->times(3)->andReturn($files);
        $downloader->shouldReceive('cleanup')->times(3);
        $original = $this->parsed('first-fingerprint');
        $changed = $this->parsed('changed-fingerprint');
        $this->mock(ParseBidDocuments::class)->shouldReceive('parse')->times(3)->andReturn($original, $original, $changed);

        $importer = app(ImportPortalBidPackage::class);
        $this->assertSame('published', $importer->handle('2026-12', '777', $owner));
        $this->assertSame('unchanged', $importer->handle('2026-12', '777', $owner));
        $this->assertSame('revision_held', $importer->handle('2026-12', '777', $owner));

        $this->assertDatabaseCount('bid_packages', 2);
        $this->assertNotNull(BidPackage::query()->where('fingerprint', 'first-fingerprint')->sole()->published_at);
        $this->assertNull(BidPackage::query()->where('fingerprint', 'changed-fingerprint')->sole()->published_at);
        $this->assertSame($owner->id, BidPackage::query()->where('fingerprint', 'changed-fingerprint')->sole()->imported_by);
    }

    public function test_incomplete_or_mismatched_package_is_not_saved(): void
    {
        $owner = User::factory()->admin()->create();
        $files = [UploadedFile::fake()->create('Lines.pdf', 1, 'application/pdf')];
        $downloader = $this->mock(DownloadPortalBidPackage::class);
        $downloader->shouldReceive('download')->once()->andReturn($files);
        $downloader->shouldReceive('cleanup')->once();
        $data = $this->parsed('incomplete-fingerprint');
        $data['payload']['coverage']['trips'] = false;
        $this->mock(ParseBidDocuments::class)->shouldReceive('parse')->once()->andReturn($data);

        try {
            app(ImportPortalBidPackage::class)->handle('2026-12', '777', $owner);
            $this->fail('An incomplete package must not be imported.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('incomplete', $exception->getMessage());
        }

        $this->assertDatabaseCount('bid_packages', 0);
    }

    public function test_package_with_missing_linked_trip_is_not_published(): void
    {
        $owner = User::factory()->admin()->create();
        $files = [UploadedFile::fake()->create('Lines.pdf', 1, 'application/pdf')];
        $downloader = $this->mock(DownloadPortalBidPackage::class);
        $downloader->shouldReceive('download')->once()->andReturn($files);
        $downloader->shouldReceive('cleanup')->once();
        $data = $this->parsed('missing-trip-fingerprint');
        $data['payload']['trips'] = [701 => ['id' => 701]];
        $this->mock(ParseBidDocuments::class)->shouldReceive('parse')->once()->andReturn($data);

        try {
            app(ImportPortalBidPackage::class)->handle('2026-12', '777', $owner);
            $this->fail('An incomplete trip set must not be imported.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('missing linked trip', $exception->getMessage());
        }

        $this->assertDatabaseCount('bid_packages', 0);
    }

    public function test_command_requires_an_accountable_active_admin(): void
    {
        config()->set('crew_bids.portal.imported_by', null);

        $this->artisan('crew-bids:import-portal', ['--period' => '2026-12', '--fleet' => '777'])
            ->expectsOutput('Configure an active, verified bid admin as CREW_BIDS_PORTAL_IMPORT_ADMIN_ID.')
            ->assertExitCode(1);

        $this->assertDatabaseCount('bid_packages', 0);
    }

    public function test_manual_command_uses_the_selected_admin_and_fleet(): void
    {
        $owner = User::factory()->admin()->create();
        $this->mock(ImportPortalBidPackage::class)->shouldReceive('handle')->once()
            ->withArgs(fn (string $period, string $fleet, User $actor): bool => $period === '2026-12' && $fleet === '777' && $actor->id === $owner->id)
            ->andReturn('published');

        $this->artisan('crew-bids:import-portal', ['--period' => '2026-12', '--fleet' => '777', '--admin-id' => (string) $owner->id])
            ->expectsOutput('B777 2026-12: published')->assertExitCode(0);
    }

    /** @return array{period: string, fleet: string, source_revision: string, fingerprint: string, payload: array<string, mixed>} */
    private function parsed(string $fingerprint): array
    {
        return [
            'period' => '2026-12', 'fleet' => '777', 'source_revision' => 'LINES_4', 'fingerprint' => $fingerprint,
            'payload' => ['coverage' => ['overview' => true, 'lines' => true, 'trips' => true], 'lines' => ['F1' => ['id' => 'F1', 'type' => 'flying', 'pairings' => [['id' => 700]]]], 'trips' => [700 => ['id' => 700]]],
        ];
    }
}
