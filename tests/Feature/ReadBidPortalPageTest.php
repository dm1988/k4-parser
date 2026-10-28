<?php

namespace Tests\Feature;

use App\Exceptions\BidPortalException;
use App\Services\CrewBids\ReadBidPortalPage;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReadBidPortalPageTest extends TestCase
{
    public function test_accepts_repeated_radio_names_without_including_unrelated_settings_in_candidate_fields(): void
    {
        $reader = app(ReadBidPortalPage::class);

        $sheet = $reader->bidSheet($this->fixture('bid-sheet'));
        $fields = $reader->replacement($sheet, ['F1']);

        $this->assertSame(3, count($sheet['slots']));
        $this->assertSame([
            'ctl00$ContentPlaceHolder1$tb_l1' => '1', 'ctl00$ContentPlaceHolder1$tb_l16' => '', 'ctl00$ContentPlaceHolder1$tb_l31' => '',
        ], $fields);
    }

    #[DataProvider('ambiguousFields')]
    public function test_still_rejects_duplicate_or_mixed_type_fields(string $field): void
    {
        $html = str_replace('</form>', $field.'</form>', $this->fixture('bid-sheet'));

        $this->expectException(BidPortalException::class);
        $this->expectExceptionMessage('ambiguous form fields');
        app(ReadBidPortalPage::class)->bidSheet($html);
    }

    public static function ambiguousFields(): array
    {
        return [
            'duplicate hidden state' => ['<input type="hidden" name="__VIEWSTATE" value="synthetic-duplicate">'],
            'duplicate choice' => ['<input type="text" name="ctl00$ContentPlaceHolder1$tb_c1" value="1">'],
            'mixed radio/text' => ['<input type="text" name="ctl00$ContentPlaceHolder1$CBT" value="synthetic-mixed">'],
            'mixed text/radio' => ['<input type="radio" name="ctl00$ContentPlaceHolder1$tb_l1" value="synthetic-mixed">'],
        ];
    }

    public function test_reads_the_private_saved_bid_sheet_layout_when_available(): void
    {
        $path = storage_path('app/private/Bid_Packages/HTML/view-source_https___crewbids.kalittaair.com_Pages_BidSheet.aspx.html');
        if (! is_file($path)) {
            $this->markTestSkipped('The private saved portal layout is unavailable.');
        }
        $document = new DOMDocument;
        @$document->loadHTML(file_get_contents($path), LIBXML_NONET);
        $rows = (new DOMXPath($document))->query('//td[contains(@class, "line-content")]');
        $html = '';
        foreach ($rows as $row) {
            $html .= $row->textContent."\n";
        }

        $sheet = app(ReadBidPortalPage::class)->bidSheet($html);

        $this->assertGreaterThan(0, count($sheet['slots']));
    }

    public function test_reads_fresh_login_state_without_remember_me(): void
    {
        $result = app(ReadBidPortalPage::class)->login($this->fixture('login'));

        $this->assertSame(['action' => './', 'fields' => [
            '__VIEWSTATE' => 'synthetic-fresh-state', '__VIEWSTATE1' => 'synthetic-split-state',
            '__EVENTVALIDATION' => 'synthetic-validation', '__EVENTTARGET' => '', '__EVENTARGUMENT' => '',
            'Login1$LoginButton' => 'Log In',
        ]], $result);
    }

    public function test_uses_displayed_ranks_instead_of_dom_or_suffix_order(): void
    {
        $html = str_replace(['tb_c31" value="3', 'tb_c16" value="2'], ['tb_c31" value="2', 'tb_c16" value="3'], $this->fixture('bid-sheet'));

        $sheet = app(ReadBidPortalPage::class)->bidSheet($html);

        $this->assertSame([
            ['rank' => 1, 'line_field' => 'ctl00$ContentPlaceHolder1$tb_l1', 'line' => 5, 'editable' => false],
            ['rank' => 2, 'line_field' => 'ctl00$ContentPlaceHolder1$tb_l31', 'line' => 7, 'editable' => false],
            ['rank' => 3, 'line_field' => 'ctl00$ContentPlaceHolder1$tb_l16', 'line' => 6, 'editable' => false],
        ], $sheet['slots']);
        $this->assertTrue($sheet['submitted_report']);
    }

    public function test_maps_confirmed_prefixes_in_exact_order_and_clears_trailing_choices_only(): void
    {
        $reader = app(ReadBidPortalPage::class);
        $sheet = $reader->bidSheet($this->fixture('bid-sheet'));

        $this->assertSame([
            'ctl00$ContentPlaceHolder1$tb_l1' => '70', 'ctl00$ContentPlaceHolder1$tb_l16' => '1', 'ctl00$ContentPlaceHolder1$tb_l31' => '99',
        ], $reader->replacement($sheet, ['R 70', 'F1', 'Q99']));
        $this->assertSame([
            'ctl00$ContentPlaceHolder1$tb_l1' => '104', 'ctl00$ContentPlaceHolder1$tb_l16' => '', 'ctl00$ContentPlaceHolder1$tb_l31' => '',
        ], $reader->replacement($sheet, ['F104']));
    }

    #[DataProvider('invalidSelections')]
    public function test_rejects_invalid_candidates_without_truncation(array $selections, string $message): void
    {
        $reader = app(ReadBidPortalPage::class);
        $sheet = $reader->bidSheet($this->fixture('bid-sheet'));

        $this->expectException(BidPortalException::class);
        $this->expectExceptionMessage($message);
        $reader->replacement($sheet, $selections);
    }

    public static function invalidSelections(): array
    {
        return [
            'empty' => [[], 'nonempty list'],
            'over capacity' => [['F1', 'F2', 'F3', 'F4'], 'observed portal capacity'],
            'numeric collision' => [['F1', 'Q1'], 'duplicate portal line numbers'],
            'unknown prefix' => [['X1'], 'unsupported line identifier'],
            'zero' => [['F0'], 'unsupported line identifier'],
            'leading zero' => [['F01'], 'unsupported line identifier'],
            'out of range' => [['F105'], 'unavailable'],
        ];
    }

    #[DataProvider('trainingCases')]
    public function test_checks_observed_training_restrictions(string $training, string $restricted, string $id, bool $allowed): void
    {
        $reader = app(ReadBidPortalPage::class);
        $html = str_replace(['hf_InTrng" value="False', 'hf_BidLines" value="'], ['hf_InTrng" value="'.$training, 'hf_BidLines" value="'.$restricted], $this->fixture('bid-sheet'));
        $sheet = $reader->bidSheet($html);
        if (! $allowed) {
            $this->expectException(BidPortalException::class);
            $this->expectExceptionMessage('unavailable');
        }

        $result = $reader->replacement($sheet, [$id]);

        $this->assertSame('70', $result['ctl00$ContentPlaceHolder1$tb_l1']);
    }

    public static function trainingCases(): array
    {
        return [
            'training eligible' => ['True', '70, 99', 'R70', true],
            'training excluded' => ['True', '99', 'R70', false],
            'no training lines' => ['True', '', 'R70', false],
            'regular eligible' => ['False', '99', 'R70', true],
            'reserved for training' => ['False', '70', 'R70', false],
        ];
    }

    #[DataProvider('invalidLayouts')]
    public function test_rejects_ambiguous_or_incomplete_layouts(string $find, string $replace): void
    {
        $html = str_replace($find, $replace, $this->fixture('bid-sheet'));

        $this->expectException(BidPortalException::class);
        app(ReadBidPortalPage::class)->bidSheet($html);
    }

    public static function invalidLayouts(): array
    {
        return [
            'rank gap' => ['tb_c31" value="3', 'tb_c31" value="4'],
            'duplicate rank' => ['tb_c31" value="3', 'tb_c31" value="2'],
            'missing pair' => ['tb_l31', 'unrecognized'],
            'duplicate name' => ['tb_l31', 'tb_l16'],
            'unknown training flag' => ['hf_InTrng" value="False', 'hf_InTrng" value="Unknown'],
            'unknown line range' => ['hf_lines" value="104', 'hf_lines" value="unknown'],
            'bad training list' => ['hf_BidLines" value="', 'hf_BidLines" value="unknown'],
            'bad old choice' => ['tb_l31" value="7', 'tb_l31" value="unknown'],
        ];
    }

    public function test_disabled_fieldsets_prevent_editability_claims(): void
    {
        $html = str_replace(' readonly', '', $this->fixture('bid-sheet'));
        $html = str_replace(['<form id="form1" method="post" action="./BidSheet.aspx">', '</form>'], ['<form id="form1" method="post" action="./BidSheet.aspx"><fieldset disabled>', '</fieldset></form>'], $html);

        $sheet = app(ReadBidPortalPage::class)->bidSheet($html);

        $this->assertSame([false, false, false], array_column($sheet['slots'], 'editable'));
    }

    private function fixture(string $name): string
    {
        return file_get_contents(base_path('tests/Fixtures/crew-bids/portal-'.$name.'.html'));
    }
}
