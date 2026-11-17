<?php

namespace Tests\Feature;

use App\Actions\CrewBids\ImportPilotSeniority;
use App\Livewire\CrewBids;
use App\Models\BidPackage;
use App\Models\PilotSeniorityList;
use App\Models\User;
use App\Services\CrewBids\CrewBidSecretStore;
use App\Services\CrewBids\ReadPilotSeniority;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Fixtures\InMemoryCrewBidSecretStore;
use Tests\Fixtures\SharedBidPortal;
use Tests\TestCase;

class PilotSeniorityTest extends TestCase
{
    use RefreshDatabase;

    private const string URL = 'https://crewbids.kalittaair.com/hyperlinks/Seniority%20July.pdf';

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(CrewBidSecretStore::class, new InMemoryCrewBidSecretStore);
    }

    public function test_manual_import_parses_pdf_and_stores_only_the_identity_fields_encrypted(): void
    {
        $admin = User::factory()->admin()->create();
        $pdf = $this->pdf($this->page());
        Http::preventStrayRequests();
        Http::fake([self::URL => Http::response($pdf, 200, ['Content-Type' => 'application/pdf'])]);

        Livewire::actingAs($admin)->test(CrewBids::class)->set('showImport', true)->set('seniorityUrl', self::URL)
            ->call('importSeniority')->assertHasNoErrors()->assertSee('Seniority list updated: 2026-07-15 · 2 pilots.');

        $list = PilotSeniorityList::current();
        $this->assertSame(['900000' => ['name' => 'Viewer, Taylor', 'seniority' => 1], '910001' => ['name' => 'Example, Avery', 'seniority' => 2]], $list->pilots);
        $this->assertSame($admin->id, $list->imported_by);
        $this->assertSame(self::URL, $list->source_url);
        $this->assertSame(hash('sha256', $pdf), $list->fingerprint);
        $this->assertArrayNotHasKey('pilots', $list->toArray());
        $this->assertStringNotContainsString('Taylor', $list->getRawOriginal('pilots'));
        $this->assertStringNotContainsString('1/2/1980', json_encode($list->getAttributes()));
        Http::assertSentCount(1);
    }

    public function test_reimport_is_idempotent_newer_roster_preserves_links_and_older_roster_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $user->forceFill(['crew_bid_employee_number' => '900000', 'crew_bid_employee_verified_at' => now()])->save();
        $initial = $this->pdf($this->page());
        $next = $this->pdf(str_replace(['July 15, 2026', 'Viewer, Taylor'], ['August 15, 2026', 'Viewer, Renamed'], $this->page()));
        Http::fake([self::URL => Http::sequence()->push($initial, 200, ['Content-Type' => 'application/pdf'])->push($initial, 200, ['Content-Type' => 'application/pdf'])
            ->push($next, 200, ['Content-Type' => 'application/pdf'])->push($initial, 200, ['Content-Type' => 'application/pdf'])]);
        $action = app(ImportPilotSeniority::class);
        $original = $action->handle(self::URL, $admin);
        $this->assertEquals($original->getRawOriginal(), $action->handle(self::URL, $admin)->getRawOriginal());
        $updated = $action->handle(self::URL, $admin);
        $this->assertSame('Viewer, Renamed', $updated->pilots['900000']['name']);
        $this->assertSame('900000', $user->fresh()->crew_bid_employee_number);
        try {
            $action->handle(self::URL, $admin);
            $this->fail('An old list replaced a newer list.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('older seniority list', $exception->getMessage());
        }
        $this->assertEquals($updated->getRawOriginal(), PilotSeniorityList::current()->getRawOriginal());
        $this->assertDatabaseCount('pilot_seniority_lists', 1);
    }

    #[DataProvider('unsafeLinks')]
    public function test_only_direct_kalitta_pdf_links_are_requested(string $url): void
    {
        $admin = User::factory()->admin()->create();
        Http::preventStrayRequests();
        Http::fake();
        try {
            app(ImportPilotSeniority::class)->handle($url, $admin);
            $this->fail('An unsafe URL was accepted.');
        } catch (RuntimeException) {
            Http::assertNothingSent();
        }
    }

    public static function unsafeLinks(): array
    {
        return [['https://example.com/roster.pdf'], ['http://crewbids.kalittaair.com/hyperlinks/list.pdf'],
            ['https://crewbids.kalittaair.com/hyperlinks/..%2Flist.pdf'], ['https://crewbids.kalittaair.com/hyperlinks/list.pdf?secret=value'],
            ['https://crewbids.kalittaair.com@127.0.0.1/hyperlinks/list.pdf']];
    }

    #[DataProvider('badDocuments')]
    public function test_failed_import_preserves_the_previous_roster(string $scenario): void
    {
        $admin = User::factory()->admin()->create();
        $before = PilotSeniorityList::factory()->create()->fresh()->getRawOriginal();
        $page = $this->page();
        $body = match ($scenario) {
            'html' => '<html>Login</html>',
            'oversized' => '%PDF-'.str_repeat('x', 8 * 1024 * 1024),
            'duplicate employee' => $this->pdf(str_replace('910001', '900000', $page)),
            'missing rank' => $this->pdf(str_replace('910001 2', '910001 3', $page)),
            'malformed row' => $this->pdf(str_replace('910001 2', 'invalid employee row', $page)),
            'missing footer' => $this->pdf(substr($page, 0, strrpos($page, 'July'))),
            default => $this->pdf($page),
        };
        Http::fake([self::URL => $scenario === 'connection' ? Http::failedConnection() : Http::response($body, $scenario === 'redirect' ? 302 : 200, ['Content-Type' => $scenario === 'html' ? 'text/html' : 'application/pdf', 'Location' => 'https://example.com/private'])]);
        try {
            app(ImportPilotSeniority::class)->handle(self::URL, $admin);
            $this->fail('An invalid source replaced the roster.');
        } catch (RuntimeException) {
            $this->assertSame($before, PilotSeniorityList::current()->getRawOriginal());
        }
        Http::assertSentCount(1);
    }

    public static function badDocuments(): array
    {
        return array_map(static fn (string $scenario): array => [$scenario], ['html', 'redirect', 'connection', 'oversized', 'duplicate employee', 'missing rank', 'malformed row', 'missing footer']);
    }

    public function test_parser_requires_consistent_dates_and_contiguous_rows_across_pages(): void
    {
        $first = $this->page('900000 1Viewer, Taylor 777 FO YIP 1/1/2020 1/1/2020 1/2/1980');
        $second = $this->page('910001 2Example, Avery 747 CA YIP 1/1/2020 1/1/2020 1/2/1980');
        $reader = app(ReadPilotSeniority::class);
        $this->assertCount(2, $reader->readPages([$first, $second])['pilots']);
        $this->expectException(RuntimeException::class);
        $reader->readPages([$first, str_replace('July 15', 'July 16', $second)]);
    }

    public function test_import_requires_admin_authorization_even_when_livewire_method_is_called_directly(): void
    {
        $user = User::factory()->crewBidsAccess()->create();
        Http::fake();
        Livewire::actingAs($user)->test(CrewBids::class)->assertDontSee('Update pilot seniority list')
            ->set('seniorityUrl', self::URL)->call('importSeniority')->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_link_verifies_portal_account_without_reading_global_report_or_changing_another_user(): void
    {
        $user = User::factory()->crewBidsAccess()->create();
        $other = User::factory()->crewBidsAccess()->create();
        PilotSeniorityList::factory()->create();
        $package = BidPackage::factory()->create();
        $http = SharedBidPortal::fake();

        $this->actingAs($user)->post(route('crew-bids.employees.read', $package), ['operation' => 'link', 'employee' => '900000', 'portal_password' => 'synthetic-password', 'seat' => 'FO'])
            ->assertRedirect()->assertSessionHas('crew_bid_sync_notice')->assertSessionMissing('_old_input');

        $linked = $user->fresh();
        $this->assertSame('900000', $linked->crew_bid_employee_number);
        $this->assertNotNull($linked->crew_bid_employee_verified_at);
        $this->assertNull($other->fresh()->crew_bid_employee_number);
        $this->assertStringNotContainsString('900000', $linked->getRawOriginal('crew_bid_employee_number'));
        $this->assertArrayNotHasKey('crew_bid_employee_number', $linked->toArray());
        $this->assertFalse($linked->isFillable('crew_bid_employee_number'));
        $this->assertStringNotContainsString('synthetic-password', json_encode(session()->all()));
        $this->assertDatabaseCount('crew_bid_snapshots', 0);
        $http->assertSentCount(3);
    }

    public function test_wrong_authenticated_account_does_not_replace_an_existing_link(): void
    {
        $user = User::factory()->crewBidsAccess()->create();
        $user->forceFill(['crew_bid_employee_number' => '900000', 'crew_bid_employee_verified_at' => now()])->save();
        PilotSeniorityList::factory()->create();
        $package = BidPackage::factory()->create();
        SharedBidPortal::fake();
        $this->actingAs($user)->post(route('crew-bids.employees.read', $package), ['operation' => 'link', 'employee' => '910001', 'portal_password' => 'synthetic-password', 'seat' => 'FO'])
            ->assertRedirect()->assertSessionHas('crew_bid_sync_error', 'The authenticated portal account does not match the supplied employee number.');
        $this->assertSame('900000', $user->fresh()->crew_bid_employee_number);
    }

    public function test_missing_roster_employee_rejects_link_before_portal_login(): void
    {
        $user = User::factory()->crewBidsAccess()->create();
        $package = BidPackage::factory()->create();
        $http = SharedBidPortal::fake();
        $this->actingAs($user)->post(route('crew-bids.employees.read', $package), ['operation' => 'link', 'employee' => '900000', 'portal_password' => 'synthetic-password', 'seat' => 'FO'])
            ->assertRedirect()->assertSessionHas('crew_bid_sync_error');
        $this->assertNull($user->fresh()->crew_bid_employee_number);
        $http->assertNothingSent();
    }

    #[DataProvider('failedLogins')]
    public function test_rejected_or_expired_portal_login_preserves_the_existing_link(string $scenario): void
    {
        $user = User::factory()->crewBidsAccess()->create();
        $user->forceFill(['crew_bid_employee_number' => '910001', 'crew_bid_employee_verified_at' => now()])->save();
        $before = $user->fresh()->getRawOriginal();
        PilotSeniorityList::factory()->create();
        $package = BidPackage::factory()->create();
        SharedBidPortal::fake($scenario);
        $this->actingAs($user)->post(route('crew-bids.employees.read', $package), ['operation' => 'link', 'employee' => '900000', 'portal_password' => 'synthetic-password', 'seat' => 'FO'])
            ->assertRedirect()->assertSessionHas('crew_bid_sync_error')->assertSessionMissing('_old_input');
        $this->assertSame($before, $user->fresh()->getRawOriginal());
        $this->assertStringNotContainsString('synthetic-password', json_encode(session()->all()));
    }

    public static function failedLogins(): array
    {
        return [['rejected-login'], ['expired-login']];
    }

    #[DataProvider('deniedAccounts')]
    public function test_account_link_requires_an_active_verified_authorized_app_login(string $state): void
    {
        $user = User::factory()->crewBidsAccess()->create();
        if ($state !== 'guest') {
            $user->forceFill(match ($state) {
                'unverified' => ['email_verified_at' => null],
                'inactive' => ['is_active' => false],
                default => ['crew_bids_access' => false],
            })->save();
            $this->actingAs($user);
        }
        $package = BidPackage::factory()->create();
        $http = SharedBidPortal::fake();
        $response = $this->post(route('crew-bids.employees.read', $package), ['operation' => 'link', 'employee' => '900000', 'portal_password' => 'synthetic-password', 'seat' => 'FO']);
        match ($state) {
            'guest' => $response->assertRedirect(route('login')),
            'unverified' => $response->assertRedirect(route('verification.notice')),
            default => $response->assertForbidden(),
        };
        $http->assertNothingSent();
        $this->assertNull($user->fresh()->crew_bid_employee_number);
    }

    public static function deniedAccounts(): array
    {
        return [['guest'], ['unverified'], ['inactive'], ['no access']];
    }

    private function page(?string $rows = null): string
    {
        $rows ??= "900000 1Viewer, Taylor 777 FO YIP 1/1/2020 1/1/2020 1/2/1980\n910001 2 Example, Avery 747 CA YIP 1/1/2020 1/1/2020 1/2/1980";

        return "PILOT'S SENIORITY LIST\nJuly 15, 2026\nEmp. No.Sen No. Name Type ClassBase 121 DOH Long Date DOB Mgt. Lv Remarks\n".$rows."\nJuly 15, 2026";
    }

    private function pdf(string $text): string
    {
        $stream = "BT /F1 10 Tf 12 TL 50 750 Td\n";
        foreach (explode("\n", trim($text)) as $line) {
            $stream .= '('.str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line).") Tj T*\n";
        }
        $stream .= 'ET';
        $objects = ['<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream"];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf."trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }
}
