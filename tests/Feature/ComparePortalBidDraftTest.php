<?php

namespace Tests\Feature;

use App\Exceptions\BidPortalException;
use App\Services\CrewBids\ComparePortalBidDraft;
use App\Services\CrewBids\ReadBidPortalPage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ComparePortalBidDraftTest extends TestCase
{
    #[DataProvider('candidateChanges')]
    public function test_reports_changes_in_bid_sheet_form_values_without_exposing_choices(array $selections, array $expected): void
    {
        $sheet = app(ReadBidPortalPage::class)->bidSheet(file_get_contents(base_path('tests/Fixtures/crew-bids/portal-bid-sheet.html')));

        $changes = app(ComparePortalBidDraft::class)->handle($sheet, $selections);

        $this->assertSame($expected, $changes);
    }

    public static function candidateChanges(): array
    {
        return [
            'same form order' => [['F5', 'F6', 'F7'], ['added' => 0, 'removed' => 0, 'reordered' => false]],
            'replacement with retained order' => [['F5', 'F8', 'F7'], ['added' => 1, 'removed' => 1, 'reordered' => false]],
            'relative reorder' => [['F7', 'F8', 'F5'], ['added' => 1, 'removed' => 1, 'reordered' => true]],
        ];
    }

    public function test_rejects_ambiguous_existing_form_choices(): void
    {
        $html = str_replace('tb_l16" value="6', 'tb_l16" value="5', file_get_contents(base_path('tests/Fixtures/crew-bids/portal-bid-sheet.html')));
        $sheet = app(ReadBidPortalPage::class)->bidSheet($html);

        $this->expectException(BidPortalException::class);
        $this->expectExceptionMessage('duplicate line numbers');

        app(ComparePortalBidDraft::class)->handle($sheet, ['F5']);
    }
}
