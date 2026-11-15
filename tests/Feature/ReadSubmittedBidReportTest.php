<?php

namespace Tests\Feature;

use App\Exceptions\BidPortalException;
use App\Services\CrewBids\ReadSubmittedBidReport;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReadSubmittedBidReportTest extends TestCase
{
    public function test_sorts_ranked_lines_by_printed_choice_instead_of_extraction_order(): void
    {
        $text = "3 21\n1 8\n2 14\nbid\nchoice\nbid\nline\nSubmitted\nBid Data";

        $this->assertSame([8, 14, 21], app(ReadSubmittedBidReport::class)->text($text));
    }

    #[DataProvider('invalidReports')]
    public function test_rejects_incomplete_or_ambiguous_reports(string $text): void
    {
        $this->expectException(BidPortalException::class);

        app(ReadSubmittedBidReport::class)->text($text);
    }

    public static function invalidReports(): array
    {
        $valid = "1 8\n2 14\nbid\nchoice\nbid\nline\nSubmitted\nBid Data";

        return [
            'empty' => [''],
            'missing rank' => [str_replace('2 14', '3 14', $valid)],
            'duplicate rank' => [str_replace('2 14', '1 14', $valid)],
            'duplicate line' => [str_replace('2 14', '2 8', $valid)],
            'unsupported row' => [$valid."\nOther Report"],
            'missing title' => [str_replace('Submitted', '', $valid)],
            'missing header' => [str_replace("bid\nline", '', $valid)],
            'rank beyond capacity' => [str_replace('2 14', '316 14', $valid)],
            'zero line' => [str_replace('2 14', '2 0', $valid)],
        ];
    }

    public function test_rejects_unreadable_pdf_content(): void
    {
        $this->expectException(BidPortalException::class);

        app(ReadSubmittedBidReport::class)->pdf('not a PDF');
    }

    public function test_reads_the_private_report_without_exposing_its_bid_values_when_available(): void
    {
        $path = storage_path('app/private/Bid_Packages/BidLineDataReport.pdf');
        if (! is_file($path)) {
            $this->markTestSkipped('The private report is unavailable.');
        }

        $lines = app(ReadSubmittedBidReport::class)->pdf(file_get_contents($path));

        $this->assertCount(43, $lines);
        $this->assertCount(43, array_unique($lines));
        $this->assertSame([], array_filter($lines, fn (int $line): bool => $line < 1 || $line > 102));
    }
}
