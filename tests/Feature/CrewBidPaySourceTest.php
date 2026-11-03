<?php

namespace Tests\Feature;

use App\Livewire\CrewBids;
use App\Models\BidPackage;
use App\Models\BidWorkspace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CrewBidPaySourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_originals_restore_distinct_pay_without_changing_published_payload_or_rankings(): void
    {
        [$package, $paths] = $this->legacyPackage();
        $user = User::factory()->admin()->withCrewPaySettings('2024-07-29')->create();
        $workspace = BidWorkspace::factory()->create(['user_id' => $user->id, 'bid_package_id' => $package->id, 'selections' => ['F2', 'F1']]);
        $original = $package->payload;

        $this->artisan('crew-bids:enrich-pay-source', ['package' => $package->id, 'files' => $paths])->assertSuccessful();

        $package->refresh();
        $this->assertSame($original, $package->payload);
        $this->assertSame(['F2', 'F1'], $workspace->fresh()->selections);
        $this->assertSame(3900, $package->pay_source['payload']['lines']['F1']['source_credit']);
        $this->assertArrayNotHasKey('pay_source', $package->toArray());
        $this->assertStringNotContainsString('source_credit', DB::table('bid_packages')->where('id', $package->id)->value('pay_source'));
        Livewire::actingAs($user)->test(CrewBids::class)->assertSee('$13,330.40')->assertSee('$15,829.85')
            ->assertSet('selections', ['F2', 'F1'])->assertViewHas('allLines', fn (array $lines): bool => $lines['F1']['pay_estimate']->cents === 1333040 && $lines['F2']['pay_estimate']->cents === 1582985);
    }

    public function test_different_source_bytes_are_rejected_without_attaching_pay_metadata(): void
    {
        [$package, $paths] = $this->legacyPackage();
        file_put_contents($paths[0], "\nchanged", FILE_APPEND);

        $this->artisan('crew-bids:enrich-pay-source', ['package' => $package->id, 'files' => $paths])->assertFailed();

        $this->assertNull($package->fresh()->pay_source);
    }

    public function test_metadata_is_ignored_when_it_no_longer_matches_the_package_fingerprint(): void
    {
        [$package, $paths] = $this->legacyPackage();
        $this->artisan('crew-bids:enrich-pay-source', ['package' => $package->id, 'files' => $paths])->assertSuccessful();
        $package->update(['fingerprint' => str_repeat('a', 64)]);
        $user = User::factory()->admin()->withCrewPaySettings('2024-07-29')->create();

        Livewire::actingAs($user)->test(CrewBids::class)
            ->assertViewHas('allLines', fn (array $lines): bool => $lines['F1']['pay_estimate']->cents === null);
    }

    public function test_source_duties_supply_combined_line_value_monthly_breakdown_and_sort_without_private_inputs(): void
    {
        $trips = "Trip Report\nPlan: 202611 / 777 / LINES_4 / 94.PUBLISH\nTrip Id: 700\nShow Time 08:00\n30Nov Mo K4 1ANC-LAX09:0010:001:00\nDebriefing 10:30\nShow Time 08:00\n01Dec Tu DH K4 2LAX-ANC09:0010:00 -\nDebriefing 10:30\nCredit Time:1:30\nBlock Time:1:00\nTrip Id: 701\nShow Time 08:00\n02Nov Mo K4 2ANC-LAX09:0012:003:00\nDebriefing 12:30\nCredit Time:65:30\nBlock Time:65:30\n";
        $lines = "Lines\nPlan: 202611 / 777 / LINES_4 / 94.PUBLISH\nF1 - 1/2\nCredit time: 65:00\n700\nSun\nPrinted page\nF2 - 1/2\nCredit time: 65:30\n701\nMon\nPrinted page\n";
        [$package, $paths] = $this->legacyPackage($trips, $lines);
        $this->artisan('crew-bids:enrich-pay-source', ['package' => $package->id, 'files' => $paths])->assertSuccessful();
        $user = User::factory()->admin()->withCrewPaySettings('2024-07-29')->create();

        $page = Livewire::actingAs($user)->test(CrewBids::class)->set('sort', 'pay');

        $page->assertViewHas('lines', fn (array $lines): bool => array_column($lines, 'id') === ['F1', 'F2'])
            ->assertViewHas('allLines', fn (array $lines): bool => $lines['F1']['pay_estimate']->cents === 1095592)
            ->assertSee('Estimated line value')->assertSee('$10,955.92')
            ->call('loadPayDetails', 'F1')->assertHasNoErrors()->assertSee('2026-11 · Scheduled gross pay')
            ->assertSee('$10,830.95')->assertSee('2026-12 · Carry-in excess-day payment only')->assertSee('$124.97')
            ->assertSee('50% of scheduled passenger deadhead')->assertDontSee('A complete total is unavailable');
        $this->assertDatabaseCount('crew_bid_pay_profiles', 0);
    }

    public function test_included_day_confirmations_are_preserved_on_verified_recovery(): void
    {
        [$package, $paths] = $this->legacyPackage();

        $this->artisan('crew-bids:enrich-pay-source', ['package' => $package->id, 'files' => $paths, '--included-occupied-day' => ['F1:2026-11-01']])->assertSuccessful();
        $this->artisan('crew-bids:enrich-pay-source', ['package' => $package->id, 'files' => $paths])->assertSuccessful();

        $package->refresh();
        $this->assertSame(['2026-11-01'], $package->pay_source['payload']['lines']['F1']['pay_included_occupied_dates']);
        $this->assertArrayNotHasKey('pay_included_occupied_dates', $package->pay_source['payload']['lines']['F2']);
    }

    public function test_unknown_day_confirmations_cannot_change_verified_pay_metadata(): void
    {
        [$package, $paths] = $this->legacyPackage();
        $this->artisan('crew-bids:enrich-pay-source', ['package' => $package->id, 'files' => $paths])->assertSuccessful();
        $original = $package->fresh()->pay_source;

        $this->artisan('crew-bids:enrich-pay-source', ['package' => $package->id, 'files' => $paths, '--included-occupied-day' => ['F1:2026-11-15']])->assertFailed();

        $this->assertSame($original, $package->fresh()->pay_source);
    }

    public function test_source_duty_report_before_midnight_belongs_to_the_day_before_its_first_departure(): void
    {
        $trips = "Trip Report\nPlan: 202611 / 777 / LINES_4 / 94.PUBLISH\nTrip Id: 700\nShow Time 23:30\n01Dec Tu K4 1ANC-LAX00:3001:301:00\nDebriefing 02:00\nCredit Time:1:00\nBlock Time:1:00\n";
        [$package, $paths] = $this->legacyPackage($trips);

        $this->artisan('crew-bids:enrich-pay-source', ['package' => $package->id, 'files' => $paths])->assertSuccessful();

        $this->assertSame([['start_date' => '2026-11-30', 'end_date' => '2026-12-01', 'leg_indexes' => [0]]], $package->fresh()->pay_source['payload']['trips'][700]['duties']);
    }

    /** @return array{BidPackage, list<string>} */
    private function legacyPackage(?string $tripText = null, ?string $lineText = null): array
    {
        Storage::fake('local');
        $lines = $lineText ?? "Lines\nPlan: 202611 / 777 / LINES_4 / 94.PUBLISH\nF1 - 1/2\nCredit time: 65:00\n700\nSun\nPrinted page\nF2 - 1/2\nCredit time: 90:00\n701\nMon\nPrinted page\n";
        $trips = $tripText ?? "Trip Report\nPlan: 202611 / 777 / LINES_4 / 94.PUBLISH\nTrip Id: 700\nShow Time 08:00\n01Nov Su K4 1ANC-LAX09:0012:003:00\nDebriefing 12:30\nCredit Time:80:00\nBlock Time:70:00\nTrip Id: 701\nShow Time 08:00\n02Nov Mo K4 2ANC-LAX09:0012:003:00\nDebriefing 12:30\nCredit Time:95:00\nBlock Time:90:00\n";
        $paths = [];
        foreach (['Lines.pdf' => $lines, 'Trips.pdf' => $trips] as $name => $text) {
            Storage::disk('local')->put('pay-source/'.$name, $this->pdf($text));
            $paths[] = Storage::disk('local')->path('pay-source/'.$name);
        }
        $hashes = array_map(static fn (string $path): string => hash_file('sha256', $path), $paths);
        sort($hashes);
        $package = BidPackage::factory()->create(['fingerprint' => hash('sha256', implode('', $hashes))]);
        $payload = $package->payload;
        $payload['lines']['F1']['pairings'] = [['id' => '700']];
        $payload['lines']['F2'] = array_replace($payload['lines']['F1'], ['id' => 'F2', 'credit' => 5400, 'pairings' => [['id' => '701']]]);
        unset($payload['lines']['R2']);
        $package->update(['payload' => $payload]);

        return [$package, $paths];
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
