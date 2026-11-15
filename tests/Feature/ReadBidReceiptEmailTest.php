<?php

namespace Tests\Feature;

use App\Exceptions\BidPortalException;
use App\Services\CrewBids\ReadBidReceiptEmail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReadBidReceiptEmailTest extends TestCase
{
    public function test_reads_a_complete_synthetic_confirmation_without_returning_identity(): void
    {
        $lines = app(ReadBidReceiptEmail::class)->handle(
            'Bid Received - 777 - FO',
            self::body(),
            '900000',
            '777',
            'FO',
        );

        $this->assertSame([8, 14, 21], $lines);
    }

    public function test_accepts_the_observed_full_notification_intro(): void
    {
        $body = str_replace(
            'confirming that your bid has been received.',
            'confirming that your bid has been received. Please, do not reply to this email. Below, is a recording of your bid results.',
            self::body(),
        );

        $this->assertSame([8, 14, 21], app(ReadBidReceiptEmail::class)->handle('Bid Received - 777 - FO', $body, '900000', '777', 'FO'));
    }

    #[DataProvider('invalidReceipts')]
    public function test_rejects_mismatched_or_incomplete_receipts(string $subject, string $body, string $employee): void
    {
        $this->expectException(BidPortalException::class);

        app(ReadBidReceiptEmail::class)->handle($subject, $body, $employee, '777', 'FO');
    }

    public static function invalidReceipts(): array
    {
        $body = self::body();

        return [
            'wrong fleet' => ['Bid Received - 747 - FO', $body, '900000'],
            'wrong seat' => ['Bid Received - 777 - CA', $body, '900000'],
            'wrong employee' => ['Bid Received - 777 - FO', $body, '900001'],
            'missing employee' => ['Bid Received - 777 - FO', str_replace('Empl #: 900000', '', $body), '900000'],
            'duplicate employee' => ['Bid Received - 777 - FO', str_replace('Empl #: 900000', "Empl #: 900000\nEmpl #: 900000", $body), '900000'],
            'invalid employee field' => ['Bid Received - 777 - FO', str_replace('Empl #: 900000', 'Empl #: unknown', $body), '900000'],
            'missing confirmation' => ['Bid Received - 777 - FO', str_replace('confirming that your bid has been received', 'no confirmation', $body), '900000'],
            'missing header' => ['Bid Received - 777 - FO', str_replace("bid_choice\tbid_line", '', $body), '900000'],
            'no choices' => ['Bid Received - 777 - FO', str_replace("1 8\n2 14\n3 21", '', $body), '900000'],
            'missing rank' => ['Bid Received - 777 - FO', str_replace('2 14', '4 14', $body), '900000'],
            'duplicate rank' => ['Bid Received - 777 - FO', str_replace('2 14', '1 14', $body), '900000'],
            'duplicate line' => ['Bid Received - 777 - FO', str_replace('2 14', '2 8', $body), '900000'],
            'rank beyond capacity' => ['Bid Received - 777 - FO', str_replace('2 14', '316 14', $body), '900000'],
            'extra content after choices' => ['Bid Received - 777 - FO', $body."\nUnexpected footer", '900000'],
        ];
    }

    private static function body(): string
    {
        return "This is an automated notification from the bid storage server confirming that your bid has been received.\n"
            ."Name: Example, Crew Member\nEmpl #: 900000\nAvailable Days Off:\nComposite Lines:\n"
            ."CBT Testing: CBT Testing not required at this time\nbid_choice\tbid_line\n"
            ."1 8\n2 14\n3 21";
    }
}
