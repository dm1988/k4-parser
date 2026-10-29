<?php

namespace Tests\Feature;

use App\Livewire\CrewBids;
use App\Models\BidPackage;
use App\Models\User;
use App\Services\CrewBids\ParseBidDocuments;
use App\Services\CrewBids\ReadBidOverview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Smalot\PdfParser\Document;
use Smalot\PdfParser\Parser;
use Tests\TestCase;
use ZipArchive;

class BidPackageImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_normalizes_minutes_dates_reserve_alignment_and_fo_only_complement(): void
    {
        $file = $this->overview();
        $result = app(ReadBidOverview::class)->read($file->getPathname());
        $this->assertSame('2026-11', $result['period']);
        $this->assertSame('777', $result['fleet']);
        $this->assertSame(3900, $result['lines']['F1']['credit']);
        $this->assertSame(2, $result['lines']['Q2']['first_officers']);
        $this->assertSame(0, $result['lines']['Q2']['captains']);
        $this->assertSame('occupied', $result['lines']['Q2']['calendar']['2026-11-01']['status']);
        $this->assertSame('off', $result['lines']['Q2']['calendar']['2026-11-02']['status']);
        $this->assertSame('off', $result['lines']['F1']['calendar']['2026-11-02']['status']);
    }

    public function test_admin_previews_then_publishes_without_retaining_source_files(): void
    {
        $page = Livewire::actingAs(User::factory()->admin()->create())->test(CrewBids::class)
            ->set('files', [$this->overview()])->call('previewImport')->assertHasNoErrors()->assertSet('files', []);
        $draft = BidPackage::query()->sole();
        $this->assertNull($draft->published_at);
        $page->assertSet('draftId', $draft->id)->call('publishImport')->assertHasNoErrors()->assertSet('packageId', $draft->id);
        $this->assertNotNull($draft->fresh()->published_at);
        $this->assertSame(2, count($draft->payload['lines']));
    }

    public function test_failed_import_preserves_published_package_and_does_not_offer_stale_preview(): void
    {
        $package = BidPackage::factory()->create();
        Livewire::actingAs(User::factory()->admin()->create())->test(CrewBids::class)
            ->set('files', [UploadedFile::fake()->createWithContent('invalid.xlsx', 'invalid archive')])->call('previewImport')
            ->assertHasErrors('files')->assertSet('packageId', $package->id)->assertSet('draftId', null);
        $this->assertDatabaseCount('bid_packages', 1);
    }

    public function test_reimporting_a_legacy_package_creates_a_new_draft_and_reuses_current_parser_drafts(): void
    {
        $file = $this->overview();
        $content = file_get_contents($file->getPathname());
        $legacyFingerprint = hash('sha256', hash_file('sha256', $file->getPathname()));
        $prior = BidPackage::factory()->create(['fingerprint' => $legacyFingerprint]);
        $originalPayload = $prior->payload;

        $page = Livewire::actingAs(User::factory()->admin()->create())->test(CrewBids::class)
            ->set('files', [$file])->call('previewImport')->assertHasNoErrors()->assertSet('packageId', $prior->id);

        $draft = BidPackage::query()->whereKeyNot($prior->id)->sole();
        $this->assertNull($draft->published_at);
        $page->assertSet('draftId', $draft->id)
            ->set('files', [UploadedFile::fake()->createWithContent('777 Overview.xlsx', $content)])
            ->call('previewImport')->assertHasNoErrors()->assertSet('draftId', $draft->id);
        $this->assertDatabaseCount('bid_packages', 2);
        $this->assertSame($originalPayload, $prior->fresh()->payload);
    }

    public function test_mismatched_pdf_and_overview_are_rejected(): void
    {
        $document = \Mockery::mock(Document::class);
        $document->shouldReceive('getText')->once()->andReturn("Lines\nPlan: 202610 / 747 / LINES_3 / 94.PUBLISH\n");
        $this->mock(Parser::class)->shouldReceive('parseFile')->once()->andReturn($document);
        $this->expectException(ValidationException::class);
        app(ParseBidDocuments::class)->parse([$this->overview(), UploadedFile::fake()->create('Lines.pdf', 1, 'application/pdf')]);
    }

    public function test_unexpected_import_failure_retains_the_active_package_and_reports_recovery(): void
    {
        $package = BidPackage::factory()->create();
        $page = Livewire::actingAs(User::factory()->admin()->create())->test(CrewBids::class)->set('files', [$this->overview()]);
        Exceptions::fake();
        $this->mock(ParseBidDocuments::class)->shouldReceive('parse')->once()->andThrow(new \RuntimeException('Simulated import failure'));
        $page->call('previewImport')->assertHasErrors('files')->assertSee('The current package is unchanged.')->assertSet('packageId', $package->id)->assertSet('draftId', null)->assertSet('files', []);
        Exceptions::assertReported(\RuntimeException::class);
        $this->assertDatabaseCount('bid_packages', 1);
    }

    public function test_trip_report_spanning_pages_and_midnight_keeps_layover_dates_occupied(): void
    {
        $document = \Mockery::mock(Document::class);
        $text = "Trip Report\nPlan: 202611 / 777 / LINES_4 / 94.PUBLISH\nTrip Id: 700\nShow Time 23:00\tCrew: 1/2\n01Nov Su K4 1ANC-LAX23:3001:00\t3:00 77X\nDebriefing 01:30\tRest27:00\nPrinted page\nTrip Report\nPlan: 202611 / 777 / LINES_4 / 94.PUBLISH\nShow Time 20:00\tDuty Days:3\n03Nov Tu DH K4 2LAX-ANC22:0002:00 - 74Y\nDebriefing 02:30\tRest  - \n";
        $document->shouldReceive('getText')->once()->andReturn(implode("\0", str_split($text)));
        $this->mock(Parser::class)->shouldReceive('parseFile')->once()->andReturn($document);
        $result = app(ParseBidDocuments::class)->parse([$this->overview(), UploadedFile::fake()->create('Trips.pdf', 1, 'application/pdf')]);
        $this->assertSame('2026-11-04', $result['payload']['trips'][700]['end_date']);
        $this->assertSame('occupied', $result['payload']['lines']['F1']['calendar']['2026-11-02']['status']);
        $this->assertSame('Trip 700 (includes layover/rest)', $result['payload']['lines']['F1']['calendar']['2026-11-02']['activity']);
        $this->assertTrue($result['payload']['trips'][700]['legs'][1]['deadhead']);
        $this->assertSame('K4 1', $result['payload']['trips'][700]['legs'][0]['flight']);
        $this->assertSame('3:00', $result['payload']['trips'][700]['legs'][0]['block']);
        $this->assertSame('27:00', $result['payload']['trips'][700]['legs'][0]['rest_after']);
        $this->assertSame(3, $result['payload']['trips'][700]['duty_days']);
        $this->assertSame('1 CA, 2 FO', $result['payload']['trips'][700]['crew_label']);
        $this->assertSame('line_source', $result['payload']['lines']['F1']['credit_basis']);
        $this->assertNull($result['payload']['lines']['F1']['carry_premium_seconds']);
        $this->assertNull($result['payload']['lines']['F1']['block']);
        $this->assertNull($result['payload']['trips'][700]['legs'][1]['block']);
        $this->assertNull($result['payload']['trips'][700]['legs'][1]['rest_after']);
    }

    public function test_trip_dates_roll_into_the_next_year_with_utc_arrival_and_release(): void
    {
        $lines = \Mockery::mock(Document::class);
        $lines->shouldReceive('getText')->once()->andReturn("Lines\nPlan: 202612 / 777 / LINES_4 / 94.PUBLISH\nF1 - 1/2\nCredit time: 65:00\nDuty days: 3\nDays Off: 28\nPrinted page\n");
        $trips = \Mockery::mock(Document::class);
        $trips->shouldReceive('getText')->once()->andReturn("Trip Report\nPlan: 202612 / 777 / LINES_4 / 94.PUBLISH\nTrip Id: 700\nShow Time 22:30\n31Dec Th K4 1ANC-LAX23:3001:003:00\nDebriefing 01:30\nShow Time 20:30\n01Jan Fr K4 2LAX-ANC22:0002:003:00\nDebriefing 02:30\n");
        $this->mock(Parser::class)->shouldReceive('parseFile')->twice()->andReturn($lines, $trips);
        $parsed = app(ParseBidDocuments::class)->parse([UploadedFile::fake()->create('Lines.pdf', 1, 'application/pdf'), UploadedFile::fake()->create('Trips.pdf', 1, 'application/pdf')]);
        $this->assertSame('2026-12-31', $parsed['payload']['trips'][700]['start_date']);
        $this->assertSame('2027-01-02', $parsed['payload']['trips'][700]['end_date']);
        $this->assertNull($parsed['payload']['trips'][700]['duty_days']);
        $this->assertNull($parsed['payload']['trips'][700]['crew_label']);
        $this->assertSame('unknown', $parsed['payload']['lines']['F1']['calendar']['2026-12-01']['status']);
    }

    public function test_private_sample_packages_parse_for_both_fleets_without_exposing_crew_names(): void
    {
        foreach ([['747', 'October'], ['777', 'November']] as [$fleet, $month]) {
            $directory = storage_path('app/private/Bid_Packages/');
            $files = [];
            foreach (['Lines.pdf', 'Trips.pdf', 'Overview.xlsx'] as $suffix) {
                $name = "$fleet $month 2026 $suffix";
                if (! is_file($directory.$name)) {
                    $this->markTestSkipped('Private sample package is unavailable.');
                }
                $files[] = new UploadedFile($directory.$name, $name, null, null, true);
            }
            $parsed = app(ParseBidDocuments::class)->parse($files);
            $this->assertSame($fleet, $parsed['fleet']);
            $this->assertGreaterThan(80, count($parsed['payload']['lines']));
            $this->assertGreaterThan(50, count($parsed['payload']['trips']));
            $this->assertGreaterThan(0, $parsed['payload']['lines']['F1']['credit']);
            $this->assertSame('off', $parsed['payload']['lines']['F1']['calendar'][$fleet === '747' ? '2026-10-01' : '2026-11-30']['status']);
        }
    }

    public function test_private_november_trip_totals_take_priority_and_preserve_carry_credit_examples(): void
    {
        $files = [];
        foreach (['Lines.pdf', 'Trips.pdf'] as $suffix) {
            $name = '777 November 2026 '.$suffix;
            $path = storage_path('app/private/Bid_Packages/'.$name);
            if (! is_file($path)) {
                $this->markTestSkipped('The private November B777 package is unavailable.');
            }
            $files[] = new UploadedFile($path, $name, null, null, true);
        }

        $parsed = app(ParseBidDocuments::class)->parse($files);

        foreach ([
            'F83' => [5415, 5850, 364050, 26100],
            'F38' => [5470, 5500, 330000, 0],
            'F26' => [5385, 5385, 323100, 0],
            'F23' => [4475, 4505, 273900, 7200],
            'F61' => [5810, 5810, 374700, 52200],
            'F46' => [4985, 5272, 329820, 27000],
            'F55' => [5140, 5140, 321900, 27000],
        ] as $id => [$block, $baseCredit, $adjustedSeconds, $carryCreditSeconds]) {
            $line = $parsed['payload']['lines'][$id];
            $this->assertSame($block, $line['block'], $id.' block');
            $this->assertSame($baseCredit, $line['base_credit'], $id.' base credit');
            $this->assertSame($adjustedSeconds, $line['credit_seconds'], $id.' adjusted credit');
            $this->assertSame($carryCreditSeconds, $line['carry_credit_seconds'], $id.' carry credit subtotal');
            $this->assertSame(intdiv($carryCreditSeconds, 2), $line['carry_premium_seconds'], $id.' carry premium');
            $this->assertSame('trip_totals', $line['credit_basis']);
        }
        $this->assertSame(5415, $parsed['payload']['lines']['F83']['source_credit']);
        $this->assertSame(26100, $parsed['payload']['lines']['F83']['deadhead_credit_seconds']);
        $this->assertCount(1, $parsed['payload']['lines']['F83']['pairings']);
        $this->assertSame(4385, $parsed['payload']['lines']['F23']['source_credit']);
    }

    public function test_carry_credit_subtotal_uses_next_month_departures_with_half_credit_deadheads_and_exact_premiums(): void
    {
        $lines = \Mockery::mock(Document::class);
        $lines->shouldReceive('getText')->once()->andReturn("Lines\nPlan: 202612 / 777 / LINES_4 / 94.PUBLISH\nF1 - 1/2\nBlock time: 0:01\nCredit time: 0:01\n700\nThu\n31Dec\n700\n");
        $trips = \Mockery::mock(Document::class);
        $trips->shouldReceive('getText')->once()->andReturn("Trip Report\nPlan: 202612 / 777 / LINES_4 / 94.PUBLISH\nTrip Id: 700\nShow Time 23:50\n31Dec Th K4 1MIA-CVG23:5900:010:02 77X\n01Jan Fr K4 2CVG-ANC00:3000:310:01 77X\n01Jan Fr DH K4 3ANC-LAX01:0001:01 - 77X\n01Jan Fr GT BUS LAX-ONT01:0501:07 - BUS Block Time:0:03\nDebriefing 01:30 Credit Time:0:04\n");
        $this->mock(Parser::class)->shouldReceive('parseFile')->twice()->andReturn($lines, $trips);

        $parsed = app(ParseBidDocuments::class)->parse([UploadedFile::fake()->create('Lines.pdf', 1, 'application/pdf'), UploadedFile::fake()->create('Trips.pdf', 1, 'application/pdf')]);

        $line = $parsed['payload']['lines']['F1'];
        $this->assertCount(1, $line['pairings']);
        $this->assertSame(3, $line['block']);
        $this->assertSame(60, $line['carry_flying_seconds']);
        $this->assertSame(30, $line['carry_deadhead_credit_seconds']);
        $this->assertSame(90, $line['carry_credit_seconds']);
        $this->assertArrayNotHasKey('pch_seconds', $line);
        $this->assertSame(45, $line['carry_premium_seconds']);
        $this->assertSame(285, $line['credit_seconds']);
        BidPackage::factory()->create(['period' => '2026-12', 'payload' => $parsed['payload']]);
        Livewire::actingAs(User::factory()->admin()->create())->test(CrewBids::class)
            ->assertViewHas('lines', fn (array $lines): bool => $lines[0]['credit_label'] === '0:04:45' && $lines[0]['block_label'] === '0:03' && $lines[0]['carry_credit_label'] === '0:01:30')
            ->assertSee('Carry credit subtotal · before premium')->assertSee('Pay Credit Hours (PCH)')
            ->assertDontSee('PCH · carry-in credit')->assertDontSee('PCH counts legs')
            ->assertSee('0:04:45')->assertSee('0:00:45');
    }

    public function test_missing_linked_trip_totals_preserve_line_values_without_a_partial_premium(): void
    {
        $lines = \Mockery::mock(Document::class);
        $lines->shouldReceive('getText')->once()->andReturn("Lines\nPlan: 202611 / 777 / LINES_4 / 94.PUBLISH\nF1 - 1/2\nBlock time: 4:00\nCredit time: 5:00\n700\nSun\n701\n");
        $trips = \Mockery::mock(Document::class);
        $trips->shouldReceive('getText')->once()->andReturn("Trip Report\nPlan: 202611 / 777 / LINES_4 / 94.PUBLISH\nTrip Id: 700\nShow Time 18:00\n01Dec Tu K4 1MIA-CVG19:0021:002:00 77X Block Time:2:00\nDebriefing 21:30 Credit Time:3:00\n");
        $this->mock(Parser::class)->shouldReceive('parseFile')->twice()->andReturn($lines, $trips);

        $parsed = app(ParseBidDocuments::class)->parse([UploadedFile::fake()->create('Lines.pdf', 1, 'application/pdf'), UploadedFile::fake()->create('Trips.pdf', 1, 'application/pdf')]);

        $line = $parsed['payload']['lines']['F1'];
        $this->assertCount(2, $line['pairings']);
        $this->assertSame(240, $line['block']);
        $this->assertSame(300, $line['credit']);
        $this->assertSame('line_source', $line['credit_basis']);
        $this->assertSame('line_source', $line['block_basis']);
        $this->assertNull($line['carry_credit_seconds']);
        $this->assertNull($line['carry_premium_seconds']);
    }

    private function overview(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'bid-overview-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/></Types>');
        $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="777 Overview" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Target="worksheets/sheet1.xml"/></Relationships>');
        $header = $this->cell('A1', 'Line Name').$this->cell('B1', 'Crew Complement').$this->cell('C1', 'Credit');
        for ($day = 1; $day <= 30; $day++) {
            $number = $day + 6;
            $column = $number > 26 ? 'A'.chr(64 + $number - 26) : chr(64 + $number);
            $header .= '<c r="'.$column.'1"><v>'.(46326 + $day).'</v></c>';
        }
        $rows = '<row r="1">'.$header.'</row><row r="2">'.$this->cell('A2', 'F1').$this->cell('B2', '1 CA 2 FO').$this->cell('C2', '65+00').$this->cell('D2', '1').$this->cell('F2', '0').$this->cell('G2', '700').'</row><row r="3">'.$this->cell('A3', 'Q2').$this->cell('B3', '2 FO ONLY').$this->cell('C3', '64+00').$this->cell('D3', 'x').'</row>';
        $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$rows.'</sheetData></worksheet>');
        $zip->close();
        $content = file_get_contents($path);
        unlink($path);

        return UploadedFile::fake()->createWithContent('777 Overview.xlsx', $content);
    }

    private function cell(string $reference, string $value): string
    {
        return '<c r="'.$reference.'" t="inlineStr"><is><t>'.htmlspecialchars($value, ENT_XML1).'</t></is></c>';
    }
}
