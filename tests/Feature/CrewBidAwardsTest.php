<?php

namespace Tests\Feature;

use App\Filament\Resources\BidAwardReports\BidAwardReportResource;
use App\Filament\Resources\BidAwardReports\Pages\ListBidAwardReports;
use App\Livewire\CrewBids;
use App\Models\BidAwardReport;
use App\Models\BidPackage;
use App\Models\BidWorkspace;
use App\Models\User;
use App\Services\CrewBids\AwardedBidSchedule;
use App\Services\CrewBids\ParseBidAwards;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class CrewBidAwardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_import_separates_ids_and_retains_a_missing_astro_id(): void
    {
        $file = $this->pdf();
        $data = app(ParseBidAwards::class)->parse($file->getPathname());

        $this->assertSame('2026-11', $data['period']);
        $this->assertSame('777', $data['fleet']);
        $this->assertSame('1001', $data['awards']['910001']['astro_id']);
        $this->assertSame('910001', $data['awards']['910001']['crew_id']);
        $this->assertSame('1', $data['awards']['910001']['line']);
        $this->assertSame(2, $data['awards']['910001']['choice']);
        $this->assertNull($data['awards']['910002']['astro_id']);
        $this->assertCount(3, $data['awards']);
    }

    public function test_admin_uploads_through_filament_and_duplicate_imports_keep_one_report(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->admin()->create());
        $package = BidPackage::factory()->create();

        Livewire::test(ListBidAwardReports::class)->callAction('importAwards', data: ['package_id' => $package->id, 'awardFile' => $this->pdf()])
            ->assertHasNoActionErrors()->assertNotified('Awards imported');
        Livewire::test(ListBidAwardReports::class)->callAction('importAwards', data: ['package_id' => $package->id, 'awardFile' => $this->pdf()])
            ->assertHasNoActionErrors()->assertNotified('Awards imported');

        $report = BidAwardReport::query()->sole();
        $this->assertSame($package->id, $report->bid_package_id);
        $this->assertStringNotContainsString('Example, Avery', $report->getRawOriginal('awards'));
        $this->assertArrayNotHasKey('awards', $report->toArray());
        $this->assertSame('K4 Bids', BidAwardReportResource::getNavigationGroup());
        Livewire::test(ListBidAwardReports::class)->assertCanSeeTableRecords([$report])->assertTableColumnStateSet('award_count', 3, $report);
    }

    public function test_mismatched_upload_preserves_existing_report(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->admin()->create());
        $existing = BidAwardReport::factory()->create();
        $otherMonth = BidPackage::factory()->create(['period' => '2026-12']);

        Livewire::test(ListBidAwardReports::class)->callAction('importAwards', data: ['package_id' => $otherMonth->id, 'awardFile' => $this->pdf()])
            ->assertHasActionErrors(['awardFile']);

        $this->assertDatabaseCount('bid_award_reports', 1);
        $this->assertModelExists($existing);
    }

    public function test_ambiguous_or_missing_crew_ids_are_rejected(): void
    {
        $layout = $this->layout();
        $layout = str_replace('910002', '910001', $layout);
        $this->expectException(ValidationException::class);

        app(ParseBidAwards::class)->parseLayout($layout);
    }

    public function test_crew_access_does_not_grant_awards_management(): void
    {
        $user = User::factory()->crewBidsAccess()->create();
        $report = BidAwardReport::factory()->create();
        $this->actingAs($user);

        $this->get(BidAwardReportResource::getUrl())->assertForbidden();
        $this->assertFalse(Gate::forUser($user)->allows('create', BidAwardReport::class));
        $this->assertFalse(Gate::forUser($user)->allows('delete', $report));
    }

    public function test_awards_take_over_the_workspace_and_preserve_the_private_bid_list(): void
    {
        $user = User::factory()->crewBidsAccess()->create();
        $user->forceFill(['crew_bid_employee_number' => '910001', 'crew_bid_employee_verified_at' => now()])->save();
        $report = BidAwardReport::factory()->create();
        $workspace = BidWorkspace::factory()->create(['bid_package_id' => $report->bid_package_id, 'user_id' => $user->id, 'seat' => 'FO', 'selections' => ['F1']]);
        $before = $workspace->fresh()->getRawOriginal();

        Livewire::actingAs($user)->test(CrewBids::class)->set('contextPackage', 'awards:'.$report->id)->call('changeContext')
            ->assertSee('Crew awarded the same line')->assertSee('Example, Avery')->assertSee('Sample, Morgan')
            ->assertDontSee('Unrelated, Taylor')->assertDontSee('910003')->assertDontSee('cc-bid-line', false)->assertDontSee('My bid list')
            ->set('contextPackage', (string) $report->bid_package_id)->call('changeContext')->assertSee('My bid list')->assertSet('selections', ['F1']);

        $this->assertSame($before, $workspace->fresh()->getRawOriginal());
    }

    public function test_manual_identification_is_session_scoped_and_does_not_verify_the_account(): void
    {
        $user = User::factory()->crewBidsAccess()->create();
        $report = BidAwardReport::factory()->create();

        Livewire::actingAs($user)->test(CrewBids::class)->set('contextPackage', 'awards:'.$report->id)->call('changeContext')
            ->assertSee('Enter your employee number')->assertDontSee('Example, Avery')
            ->set('awardForm.employeeNumber', '910001')->call('identifyAwardEmployee')->assertHasNoErrors()
            ->assertSee('Example, Avery')->assertSee('Sample, Morgan')->assertDontSee('Unrelated, Taylor');

        $this->assertNull($user->fresh()->crew_bid_employee_number);
        $this->assertNull($user->fresh()->crew_bid_employee_verified_at);
        $other = User::factory()->crewBidsAccess()->create();
        $this->actingAs($other);
        $this->assertTrue(app(AwardedBidSchedule::class)->forUser($report, $other)->employeeRequired);
    }

    public function test_unknown_employee_shows_no_award_without_exposing_other_lines(): void
    {
        $user = User::factory()->crewBidsAccess()->create();
        $report = BidAwardReport::factory()->create();

        Livewire::actingAs($user)->test(CrewBids::class)->set('contextPackage', 'awards:'.$report->id)->call('changeContext')
            ->set('awardForm.employeeNumber', '999999')->call('identifyAwardEmployee')
            ->assertSee('No award was found')->assertDontSee('Example, Avery')->assertDontSee('Sample, Morgan');
    }

    public function test_combined_and_per_flight_exports_include_transition_times_without_duplicate_duties(): void
    {
        $user = User::factory()->crewBidsAccess()->create();
        $user->forceFill(['crew_bid_employee_number' => '910001'])->save();
        $report = $this->withSchedule();
        $this->actingAs($user);
        config(['features.schedule_extractor.enabled' => false]);
        $data = app(AwardedBidSchedule::class)->forUser($report, $user);

        $response = $this->get(route('crew-bids.awards.calendar', $report))->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $ics = str_replace("\r\n ", '', $response->getContent());
        $this->assertSame(3, substr_count($ics, 'BEGIN:VEVENT'));
        $this->assertStringContainsString('DTSTART:20261130T220000Z', $ics);
        $this->assertStringContainsString('DTEND:20261201T073000Z', $ics);
        $this->assertStringContainsString('2026-11 · B777 · F1 Awards', $ics);
        $this->assertStringNotContainsString('Unrelated, Taylor', $ics);
        $this->assertCount(31, $data->days);
        $this->assertSame('2026-12-01', $data->days[30]['date']);
        $this->assertTrue($data->days[30]['transition']);
        $single = $this->get($data->flights[1]->flight->downloadUrl)->assertOk()->getContent();
        $this->assertSame(1, substr_count($single, 'BEGIN:VEVENT'));
        $this->assertStringContainsString('DTSTART:20261201T050000Z', $single);
    }

    #[TestWith(['2027-02', 28])]
    #[TestWith(['2028-02', 29])]
    #[TestWith(['2026-11', 30])]
    #[TestWith(['2026-12', 31])]
    public function test_awards_render_every_day_of_the_selected_month(string $period, int $days): void
    {
        $user = User::factory()->crewBidsAccess()->create();
        $user->forceFill(['crew_bid_employee_number' => '910001'])->save();
        $package = BidPackage::factory()->create(['period' => $period]);
        $report = BidAwardReport::factory()->create(['bid_package_id' => $package->id, 'period' => $period]);
        $this->actingAs($user);

        $data = app(AwardedBidSchedule::class)->forUser($report, $user);

        $this->assertCount($days, $data->days);
        $this->assertSame($period.'-01', $data->days[0]['date']);
    }

    public function test_ambiguous_line_mapping_does_not_guess_flying_or_reserve(): void
    {
        $user = User::factory()->crewBidsAccess()->create();
        $user->forceFill(['crew_bid_employee_number' => '910001'])->save();
        $report = BidAwardReport::factory()->create();
        $package = $report->package;
        $payload = $package->payload;
        $payload['lines']['Q1'] = array_replace($payload['lines']['F1'], ['id' => 'Q1']);
        $package->update(['payload' => $payload]);
        $this->actingAs($user);

        $data = app(AwardedBidSchedule::class)->forUser($report->fresh(), $user);

        $this->assertCount(2, $data->crew);
        $this->assertSame([], $data->days);
        $this->assertStringContainsString('cannot be uniquely matched', $data->warnings[0]);
    }

    public function test_export_access_is_scoped_to_current_employee_package_and_available_source(): void
    {
        $report = $this->withSchedule();
        $url = route('crew-bids.awards.calendar', $report);
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        $user = User::factory()->crewBidsAccess()->create();
        $this->actingAs($user)->get($url)->assertNotFound();
        $user->forceFill(['crew_bid_employee_number' => '999999'])->save();
        $this->get($url)->assertNotFound();
        $user->forceFill(['crew_bid_employee_number' => '910001'])->save();
        $mine = app(AwardedBidSchedule::class)->forUser($report, $user);
        $user->forceFill(['crew_bid_employee_number' => '910003'])->save();
        $this->get($mine->flights[0]->flight->downloadUrl)->assertNotFound();
        $this->get($url)->assertOk()->assertDontSee('CKS 101');
        $user->forceFill(['crew_bid_employee_number' => '910001'])->save();
        $report->package->update(['archived_at' => now()]);
        $this->get($url)->assertNotFound();
    }

    public function test_admin_removes_a_report_without_deleting_its_schedule(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $report = BidAwardReport::factory()->create();

        Livewire::test(ListBidAwardReports::class)->callAction(TestAction::make('delete')->table($report))->assertHasNoActionErrors();

        $this->assertModelMissing($report);
        $this->assertDatabaseCount('bid_packages', 1);
    }

    public function test_published_reserve_days_export_as_all_day_without_invented_duty_hours(): void
    {
        $user = User::factory()->crewBidsAccess()->create();
        $user->forceFill(['crew_bid_employee_number' => '910003'])->save();
        $report = BidAwardReport::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('crew-bids.awards.calendar', $report))->assertOk();

        $this->assertStringContainsString('DTSTART;VALUE=DATE:20261101', $response->getContent());
        $this->assertStringContainsString('DTEND;VALUE=DATE:20261102', $response->getContent());
        $this->assertStringContainsString('duty hours unavailable', $response->getContent());
        $this->assertStringNotContainsString('DTSTART:20261101T000000Z', $response->getContent());
    }

    public function test_old_packages_export_known_flights_and_explain_missing_duty_coverage(): void
    {
        $user = User::factory()->crewBidsAccess()->create();
        $user->forceFill(['crew_bid_employee_number' => '910001'])->save();
        $report = $this->withSchedule();
        $package = $report->package;
        $payload = $package->payload;
        unset($payload['trips']['700']['duties'][0]['report_at']);
        $package->update(['payload' => $payload]);
        $this->actingAs($user);

        $data = app(AwardedBidSchedule::class)->forUser($report->fresh(), $user);

        $this->assertCount(2, $data->flights);
        $this->assertSame([], $data->duties);
        $this->assertStringContainsString('Reimport the original Trips PDF', implode(' ', $data->warnings));
    }

    public function test_unrelated_flight_ids_cannot_be_downloaded_and_event_identity_survives_a_time_correction(): void
    {
        $user = User::factory()->crewBidsAccess()->create();
        $user->forceFill(['crew_bid_employee_number' => '910001'])->save();
        $report = $this->withSchedule();
        $this->actingAs($user);
        $first = app(AwardedBidSchedule::class)->forUser($report, $user);
        $identity = $first->result->parsed['calendar_events'][1]['metadata']['event_uid'];
        $package = $report->package;
        $payload = $package->payload;
        $payload['trips']['700']['legs'][0]['departure'] = '23:10';
        $package->update(['payload' => $payload]);

        $updated = app(AwardedBidSchedule::class)->forUser($report->fresh(), $user);

        $this->assertSame($identity, $updated->result->parsed['calendar_events'][1]['metadata']['event_uid']);
        $this->get(route('crew-bids.awards.event', ['report' => $report, 'eventId' => str_repeat('a', 64)]))->assertNotFound();
    }

    private function withSchedule(): BidAwardReport
    {
        $package = BidPackage::factory()->create();
        $payload = $package->payload;
        $payload['lines']['F1']['pairings'] = [['id' => '700'], ['id' => '700']];
        $payload['trips']['700'] = ['start_date' => '2026-11-30', 'end_date' => '2026-12-01', 'legs' => [
            ['date' => '2026-11-30', 'departure' => '23:00', 'arrival' => '02:00', 'origin' => 'ANC', 'destination' => 'LAX', 'flight' => 'CKS 101', 'deadhead' => false],
            ['date' => '2026-12-01', 'departure' => '05:00', 'arrival' => '07:00', 'origin' => 'LAX', 'destination' => 'JFK', 'flight' => 'CKS 102', 'deadhead' => false],
        ], 'duties' => [['report_at' => '2026-11-30T22:00:00+00:00', 'release_at' => '2026-12-01T07:30:00+00:00', 'leg_indexes' => [0, 1]]]];
        $package->update(['payload' => $payload]);

        return BidAwardReport::factory()->create(['bid_package_id' => $package->id]);
    }

    private function layout(): string
    {
        $row = static fn (array $values): string => vsprintf('%-10s%-12s%-20s%-8s%-12s%-12s%-32s%s', $values);

        return implode("\n", ['Awarded Bids 777 Fleet', $row(['Type', 'Month', 'Bid Line Awarded', ' Class', 'Astro ID', 'Crew ID', 'Name', 'Bid Choice Awarded']),
            $row(['777', '11/2026', '', '', '', '', '', '']),
            $row(['', '', '1', 'FO', '1001', '910001', 'Example, Avery', '2']),
            $row(['', '', '1', 'CA', '', '910002', 'Sample, Morgan', '1']),
            $row(['', '', '2', 'FO', '1003', '910003', 'Unrelated, Taylor', '0'])]);
    }

    private function pdf(): UploadedFile
    {
        $stream = "BT /F1 8 Tf 12 TL 20 760 Td\n";
        foreach (explode("\n", $this->layout()) as $line) {
            $stream .= '('.str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line).") Tj T*\n";
        }
        $stream .= 'ET';
        $objects = ['<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 1000 800] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>', '<< /Length '.strlen($stream).">>\nstream\n".$stream."\nendstream"];
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
        $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";

        return UploadedFile::fake()->createWithContent('awards.pdf', $pdf);
    }
}
