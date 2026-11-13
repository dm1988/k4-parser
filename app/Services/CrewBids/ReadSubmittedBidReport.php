<?php

namespace App\Services\CrewBids;

use App\Exceptions\BidPortalException;
use SensitiveParameter;
use Smalot\PdfParser\Parser;
use Throwable;

class ReadSubmittedBidReport
{
    public function __construct(private Parser $parser) {}

    /** @return list<int> */
    public function pdf(#[SensitiveParameter] string $content): array
    {
        try {
            $text = $this->parser->parseContent($content)->getText();
        } catch (Throwable) {
            throw new BidPortalException('The Submitted Bid Lines PDF could not be read.');
        }

        return $this->text($text);
    }

    /**
     * The report text extractor may emit columns out of order, so the printed
     * choice number determines the returned order.
     *
     * @return list<int>
     */
    public function text(#[SensitiveParameter] string $content): array
    {
        $choices = [];
        $lines = [];
        $labels = ['bid' => 0, 'choice' => 0, 'line' => 0, 'submitted' => 0, 'bid data' => 0];

        foreach (preg_split('/\R/u', trim($content)) ?: [] as $row) {
            $row = trim($row);
            if ($row === '') {
                continue;
            }
            if (preg_match('/\A([1-9]\d{0,2})\s+([1-9]\d{0,3})\z/u', $row, $match) === 1) {
                $rank = (int) $match[1];
                $line = (int) $match[2];
                if ($rank > 315 || isset($choices[$rank]) || isset($lines[$line])) {
                    throw new BidPortalException('The Submitted Bid Lines report has duplicate or unsupported values.');
                }
                $choices[$rank] = $line;
                $lines[$line] = true;

                continue;
            }
            $label = strtolower($row);
            if (! array_key_exists($label, $labels)) {
                throw new BidPortalException('The Submitted Bid Lines report layout is not supported.');
            }
            $labels[$label]++;
        }

        if ($choices === [] || $labels['submitted'] !== 1 || $labels['bid data'] !== 1
            || $labels['choice'] === 0 || $labels['choice'] !== $labels['line']
            || $labels['bid'] !== $labels['choice'] + $labels['line']) {
            throw new BidPortalException('The Submitted Bid Lines report is incomplete.');
        }
        ksort($choices);
        if (array_keys($choices) !== range(1, count($choices))) {
            throw new BidPortalException('The Submitted Bid Lines report has a missing choice rank.');
        }

        return array_values($choices);
    }
}
