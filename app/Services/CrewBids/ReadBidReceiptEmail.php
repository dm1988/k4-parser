<?php

namespace App\Services\CrewBids;

use App\Exceptions\BidPortalException;
use SensitiveParameter;

class ReadBidReceiptEmail
{
    /**
     * Parsing does not authenticate the sender or bind the email to a bid period.
     *
     * @return list<int>
     */
    public function handle(
        #[SensitiveParameter] string $subject,
        #[SensitiveParameter] string $body,
        #[SensitiveParameter] string $employee,
        string $fleet,
        string $seat,
    ): array {
        if (! preg_match('/\A[1-9]\d{0,9}\z/', $employee)
            || ! in_array($fleet, ['747', '777'], true)
            || ! in_array($seat, ['CA', 'FO'], true)
            || trim($subject) !== "Bid Received - {$fleet} - {$seat}"
            || ! str_contains($body, 'This is an automated notification from the bid storage server confirming that your bid has been received.')) {
            throw new BidPortalException('The bid receipt email does not match the expected confirmation format.');
        }

        $accountRows = 0;
        $accountMatches = false;
        $confirmationSeen = false;
        $listStarted = false;
        $choices = [];
        $seenLines = [];
        foreach (preg_split('/\R/u', $body) ?: [] as $row) {
            $row = trim($row);
            if ($row === '') {
                continue;
            }
            if (! $listStarted) {
                if ($row === 'This is an automated notification from the bid storage server confirming that your bid has been received. Please, do not reply to this email. Below, is a recording of your bid results.'
                    || $row === 'This is an automated notification from the bid storage server confirming that your bid has been received.') {
                    $confirmationSeen = true;
                }
                if (str_starts_with($row, 'Empl #:')) {
                    if (preg_match('/\AEmpl #:\s*([1-9]\d{0,9})\z/u', $row, $match) !== 1) {
                        throw new BidPortalException('The bid receipt email has an unsupported account field.');
                    }
                    $accountRows++;
                    $accountMatches = $match[1] === $employee;
                }
                if (preg_match('/\Abid_choice\s+bid_line\z/ui', $row) === 1) {
                    $listStarted = true;
                }

                continue;
            }

            if (preg_match('/\A([1-9]\d{0,2})\s+([1-9]\d{0,3})\z/u', $row, $match) !== 1) {
                throw new BidPortalException('The bid receipt email has an unsupported choice row.');
            }
            $rank = (int) $match[1];
            $line = (int) $match[2];
            if ($rank > 315 || isset($choices[$rank]) || isset($seenLines[$line])) {
                throw new BidPortalException('The bid receipt email has duplicate or unsupported choices.');
            }
            $choices[$rank] = $line;
            $seenLines[$line] = true;
        }

        if ($accountRows !== 1 || ! $accountMatches || ! $confirmationSeen || ! $listStarted || $choices === []) {
            throw new BidPortalException('The bid receipt email is missing an account or choice list.');
        }
        ksort($choices);
        if (array_keys($choices) !== range(1, count($choices))) {
            throw new BidPortalException('The bid receipt email has a missing choice rank.');
        }

        return array_values($choices);
    }
}
