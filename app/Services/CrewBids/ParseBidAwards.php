<?php

namespace App\Services\CrewBids;

use App\Services\Schedule\Extractor\PdfTextExtractor;
use Illuminate\Validation\ValidationException;

class ParseBidAwards
{
    public function __construct(private PdfTextExtractor $pdf) {}

    /** @return array{period: string, fleet: string, awards: array<string, array{crew_id: string, astro_id: ?string, line: string, seat: string, name: string, choice: int}>} */
    public function parse(string $path): array
    {
        return $this->parseLayout($this->pdf->extractLayout($path));
    }

    /** @return array{period: string, fleet: string, awards: array<string, array{crew_id: string, astro_id: ?string, line: string, seat: string, name: string, choice: int}>} */
    public function parseLayout(string $text): array
    {
        if (! preg_match('/Awarded Bids(?:\s*-\s*400)?\s*(?:777\s*)?Fleet/i', $text)
            || ! preg_match('/^\h*(747(?:-400)?|777)\h+(0[1-9]|1[0-2])\/(\d{4})\b/m', $text, $context)) {
            $this->invalid('The award report month and fleet could not be identified.');
        }
        $awards = [];
        $columns = null;
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $row) {
            $row = str_replace("\f", '', $row);
            if (str_contains($row, 'Bid Line Awarded') && str_contains($row, 'Crew ID')) {
                $columns = $this->columns($row);

                continue;
            }
            if (! preg_match('/^\h*(\d+)\h+(CA|FO)\b/', $row, $identity)) {
                continue;
            }
            if ($columns === null) {
                $this->invalid('An award row has no supported column header.');
            }
            $values = [];
            foreach ($columns as $index => $start) {
                $values[] = trim(substr($row, $start, isset($columns[$index + 1]) ? $columns[$index + 1] - $start : null));
            }
            [, , $astro, $crew, $name, $choice] = $values;
            $line = $identity[1];
            $seat = $identity[2];
            if (! preg_match('/\A[1-9]\d{0,3}\z/', $line)
                || ! preg_match('/\A\d{1,20}\z/', $crew) || ($astro !== '' && ! ctype_digit($astro))
                || $name === '' || ! ctype_digit($choice) || isset($awards[$crew])) {
                $this->invalid('An award row is incomplete or has an ambiguous employee or line.');
            }
            $awards[$crew] = ['crew_id' => $crew, 'astro_id' => $astro === '' ? null : $astro, 'line' => $line,
                'seat' => $seat, 'name' => $name, 'choice' => (int) $choice];
        }
        if ($awards === []) {
            $this->invalid('No supported awards were found in the report.');
        }

        return ['period' => $context[3].'-'.$context[2], 'fleet' => str_starts_with($context[1], '747') ? '747' : '777', 'awards' => $awards];
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['awardFile' => $message]);
    }

    /** @return list<int> */
    private function columns(string $header): array
    {
        $columns = [];
        foreach (['Bid Line Awarded', 'Class', 'Astro ID', 'Crew ID', 'Name', 'Bid Choice Awarded'] as $label) {
            $position = strpos($header, $label);
            if ($position === false) {
                $this->invalid('The award report columns are unsupported.');
            }
            $columns[] = $position;
        }

        return $columns;
    }
}
