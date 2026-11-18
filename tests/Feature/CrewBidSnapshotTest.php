<?php

namespace Tests\Feature;

use App\Exceptions\BidPortalException;
use App\Models\BidPackage;
use App\Models\CrewBidSnapshot;
use App\Services\CrewBids\ReadEmployeeBidPage;
use App\Services\CrewBids\SyncCrewBidSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\SharedBidPortal;
use Tests\TestCase;

class CrewBidSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_publishes_only_complete_global_report_with_continued_groups_and_confirmed_empty_bids(): void
    {
        $package = BidPackage::factory()->create(['period' => '2026-12']);
        Event::fake([RequestSending::class, ResponseReceived::class]);
        $http = SharedBidPortal::fake();

        $result = app(SyncCrewBidSnapshot::class)->handle($package, '900000', 'synthetic-password', 'FO');

        $snapshot = CrewBidSnapshot::query()->sole();
        $this->assertSame($snapshot->id, $result['snapshot']->id);
        $this->assertSame([2, 1], $snapshot->bidders[910001]['numbers']);
        $this->assertSame([], $snapshot->bidders[910002]['numbers']);
        $this->assertSame(10, $snapshot->bidders[910001]['seniority']);
        $this->assertSame('2026-12', $snapshot->period);
        $this->assertSame('777', $snapshot->fleet);
        $this->assertSame('FO', $snapshot->seat);
        $this->assertSame(4000, $snapshot->portal_package_id);
        $this->assertArrayNotHasKey('bidders', $snapshot->toArray());
        $stored = DB::table('crew_bid_snapshots')->value('bidders');
        foreach (['Example, Avery', 'synthetic-password', '900000', 'synthetic-global', 'ReportID', 'directory-state'] as $private) {
            $this->assertStringNotContainsString($private, $stored);
        }
        $http->assertSent(fn (Request $request): bool => $request->method() === 'POST' && ($request['ctl00$ContentPlaceHolder1$ddl_crewMembers'] ?? '') === 'View All');
        $http->assertSent(fn (Request $request): bool => str_contains($request->url(), 'PageNumber=2'));
        $http->assertNotSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), 'BidSheet.aspx'));
        Event::assertNothingDispatched();
        $this->assertDatabaseCount('bid_submissions', 0);
        $this->assertDatabaseCount('bid_workspaces', 0);
    }

    public function test_position_refreshes_use_fresh_state_and_replace_only_the_same_global_scope(): void
    {
        $package = BidPackage::factory()->create(['period' => '2026-12']);
        $other = CrewBidSnapshot::factory()->create(['fleet' => '747']);
        $fo = CrewBidSnapshot::factory()->create(['period' => '2026-12']);
        $http = SharedBidPortal::fake();
        $service = app(SyncCrewBidSnapshot::class);

        $ca = $service->handle($package, '900000', 'synthetic-password', 'CA')['snapshot'];
        $refreshed = $service->handle($package, '900000', 'synthetic-password', 'FO')['snapshot'];

        $this->assertSame('CA', $ca->seat);
        $this->assertSame($fo->id, $refreshed->id);
        $this->assertSame($other->bidders, $other->fresh()->bidders);
        $this->assertDatabaseCount('crew_bid_snapshots', 3);
        $http->assertSent(fn (Request $request): bool => ($request['ctl00$ContentPlaceHolder1$ddl_crewMembers'] ?? '') === 'View All' && ($request['__VIEWSTATE'] ?? '') === 'ca-state');
    }

    public function test_global_read_requires_authenticated_context_but_not_personal_bid_entry_fields(): void
    {
        $package = BidPackage::factory()->create(['period' => '2026-12']);
        $http = SharedBidPortal::fake('read-only-sheet');

        $result = app(SyncCrewBidSnapshot::class)->handle($package, '900000', 'synthetic-password', 'FO');

        $this->assertSame([2, 1], $result['snapshot']->bidders[910001]['numbers']);
        $http->assertNotSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), 'BidSheet.aspx'));
    }

    #[DataProvider('invalidReports')]
    public function test_failed_refresh_preserves_the_last_good_snapshot(string $scenario): void
    {
        $package = BidPackage::factory()->create(['period' => '2026-12']);
        $snapshot = CrewBidSnapshot::factory()->create(['period' => '2026-12', 'retrieved_at' => now()->subHour()]);
        $before = $snapshot->fresh()->getRawOriginal();
        $http = SharedBidPortal::fake($scenario);
        try {
            app(SyncCrewBidSnapshot::class)->handle($package, '900000', 'synthetic-password', 'FO');
            $this->fail('An unverified global report was published.');
        } catch (BidPortalException) {
            $this->assertSame($before, $snapshot->fresh()->getRawOriginal());
            $this->assertDatabaseCount('crew_bid_snapshots', 1);
        }
        $http->assertNotSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), 'BidSheet.aspx'));
    }

    public static function invalidReports(): array
    {
        return array_map(static fn (string $scenario): array => [$scenario], ['wrong-context', 'context-changed', 'wrong-selection', 'duplicate-seniority', 'duplicate-name', 'bad-export-session',
            'missing-bidder', 'unknown-name', 'duplicate-rank', 'missing-rank', 'pagination-changed', 'too-many-pages', 'connection',
            'gap-unconfirmed', 'gap-directory-changed', 'gap-extra-sparse', 'gap-pagination-changed', 'gap-duplicate-rank']);
    }

    #[DataProvider('verifiedChoiceRanks')]
    public function test_global_rank_gaps_are_reconciled_only_with_the_verified_individual_report(string $scenario, array $numbers, array $ranks): void
    {
        $package = BidPackage::factory()->create(['period' => '2026-12']);
        $http = SharedBidPortal::fake($scenario);

        $result = app(SyncCrewBidSnapshot::class)->handle($package, '900000', 'synthetic-password', 'FO');

        $this->assertSame($numbers, $result['snapshot']->bidders[910001]['numbers']);
        $this->assertSame($ranks, $result['snapshot']->bidders[910001]['ranks']);
        $this->assertSame([], $result['snapshot']->bidders[910002]['numbers']);
        $this->assertDatabaseCount('crew_bid_snapshots', 1);
        $http->assertSent(fn (Request $request): bool => ($request['ctl00$ContentPlaceHolder1$ddl_crewMembers'] ?? '') === '910001');
        $http->assertNotSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), 'BidSheet.aspx'));
    }

    public static function verifiedChoiceRanks(): array
    {
        return [
            'missing global rows recovered' => ['gap-recovered', [2, 74, 1], [1, 2, 3]],
            'printed gaps confirmed' => ['gap-confirmed', [2, 1], [1, 3]],
            'first slot blank confirmed' => ['gap-start', [2, 1], [2, 3]],
            'first global row recovered' => ['gap-first-recovered', [74, 2, 1], [1, 2, 3]],
            'individual pagination' => ['gap-paginated', [2, 74, 1], [1, 2, 3]],
            'repeated lines preserved' => ['gap-repeated-line', [2, 2], [1, 3]],
        ];
    }

    public function test_rank_verification_keeps_requested_exports_bound_to_the_global_report(): void
    {
        $package = BidPackage::factory()->create(['period' => '2026-12']);
        $http = SharedBidPortal::fake('gap-confirmed');

        $result = app(SyncCrewBidSnapshot::class)->handle($package, '900000', 'synthetic-password', 'FO', 'PDF');

        $this->assertSame('%PDF-synthetic-global-export', $result['download']);
        $this->assertSame([1, 3], $result['snapshot']->bidders[910001]['ranks']);
        $http->assertSent(fn (Request $request): bool => str_contains($request->url(), 'OpType=Export') && str_contains($request->url(), 'ReportID=synthetic-global'));
        $http->assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'OpType=Export') && str_contains($request->url(), 'ReportID=synthetic-individual'));
    }

    public function test_an_in_progress_refresh_blocks_a_second_login_for_the_same_scope(): void
    {
        $package = BidPackage::factory()->create(['period' => '2026-12']);
        $http = SharedBidPortal::fake();
        $lock = Cache::lock('crew-bid-snapshot:2026-12:777:FO', 300);
        $this->assertTrue($lock->get());
        try {
            app(SyncCrewBidSnapshot::class)->handle($package, '900000', 'synthetic-password', 'FO');
            $this->fail('A concurrent refresh was allowed.');
        } catch (BidPortalException $exception) {
            $this->assertStringContainsString('already running', $exception->getMessage());
        } finally {
            $lock->release();
        }
        $http->assertNothingSent();
    }

    public function test_global_reader_accepts_the_observed_84_page_report_and_no_choice_groups(): void
    {
        $html = '<div id="oReportDiv"><table><tr><td>Current Bid Status For</td></tr></table><table id="t_3"><tr><td>Name</td><td>Choice</td><td>Line</td></tr><tr><td>Example, Avery</td><td></td><td></td></tr></table><table><tr><td>1 of 84</td></tr></table></div>';

        $report = app(ReadEmployeeBidPage::class)->globalReport($html, ['Example, Avery' => '910001']);

        $this->assertSame(84, $report['pages']);
        $this->assertSame([910001 => []], $report['groups']);
    }

    public function test_global_reader_reads_the_supplied_nested_frame_structure_without_fixed_header_copies(): void
    {
        $html = file_get_contents(base_path('tests/Fixtures/crew-bids/portal-global-report.html'));

        $report = app(ReadEmployeeBidPage::class)->globalReport($html, ['Example, Avery' => '910001', 'Sample, Morgan' => '910002']);

        $this->assertSame(116, $report['pages']);
        $this->assertSame([910001 => [1 => 2, 2 => 1], 910002 => [1 => 4, 2 => 3]], $report['groups']);
        $this->assertSame('910002', $report['last']);
    }

    public function test_global_reports_preserve_repeated_line_numbers_at_different_ranks(): void
    {
        $package = BidPackage::factory()->create(['period' => '2026-12']);
        $http = SharedBidPortal::fake('duplicate-line');

        $result = app(SyncCrewBidSnapshot::class)->handle($package, '900000', 'synthetic-password', 'FO');

        $this->assertSame([2, 2], $result['snapshot']->bidders[910001]['numbers']);
        $http->assertSent(fn (Request $request): bool => str_contains($request->url(), 'PageNumber=2'));
    }

    #[DataProvider('exports')]
    public function test_exports_use_the_same_widget_with_independent_report_rendering_sessions(string $format, string $prefix): void
    {
        $package = BidPackage::factory()->create(['period' => '2026-12']);
        $http = SharedBidPortal::fake('separate-render-sessions');

        $result = app(SyncCrewBidSnapshot::class)->handle($package, '900000', 'synthetic-password', 'FO', $format);

        $this->assertSame($prefix.'synthetic-global-export', $result['download']);
        $http->assertSent(fn (Request $request): bool => str_contains($request->url(), 'ReportID=synthetic-export-session') && str_ends_with($request->url(), 'Format='.$format));
    }

    public static function exports(): array
    {
        return [['PDF', '%PDF-'], ['Excel', "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1"]];
    }
}
