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
        $text = "Trip Report\nPlan: 202611 / 777 / LINES_4 / 94.PUBLISH\nTrip Id: 700\nShow Time 23:00\n01Nov Su K4 1ANC-LAX23:3001:00\t3:00 77X\nDebriefing 01:30\tRest27:00\nPrinted page\nTrip Report\nPlan: 202611 / 777 / LINES_4 / 94.PUBLISH\nShow Time 20:00\n03Nov Tu DH K4 2LAX-ANC22:0002:00 - 74Y\nDebriefing 02:30\tRest  - \n";
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
