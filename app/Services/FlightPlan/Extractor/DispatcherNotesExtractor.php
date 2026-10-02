<?php

namespace App\Services\FlightPlan\Extractor;

use Illuminate\Support\Str;

class DispatcherNotesExtractor
{
    /**
     * @return array{
     *     data: list<string>,
     *     source_fragments: array<string, string>
     * }
     */
    public function extract(string $text): array
    {
        $header = $this->dispatchHeader($text);

        if ($header === '') {
            return ['data' => [], 'source_fragments' => []];
        }

        /** @var list<array{offset: int, text: string}> $notes */
        $notes = [];

        $this->addSimpleNote($notes, $header, '/\b\d{2}Z\s+SIGWX\s+FCST\s+.+?(?=\s*\*{3}|\s*--)/is');
        $this->addSimpleNote($notes, $header, '/\bADDNL\s+\d+(?:\.\d+)?\s+FOR\s+.+?(?=\s*\*{3}|\s*--)/is');
        $this->addUnavailableSlotNote($notes, $header);
        $this->addSimpleNote($notes, $header, '/\bFUEL\s+BURN\s+INCLUDES\s+\d+(?:\.\d+)?%\s+PENALTY\s+FOR\s+(?:PERISHABLES|PRSHBLS)\b/i');
        $this->addSimpleNote($notes, $header, '/\bPLANNED\s+FL\d{3}\s+THROUGH\s+NAT\s+HLA\s+ON\s+A\s+RANDOM\s+ROUTE\b/i');
        $this->addEnrouteSigwxNote($notes, $header);

        if (! Str::contains($header, 'ETOPS PRE-DEPARTURE BRIEF COMPLETE', true)) {
            $this->addForecastRunwayNote($notes, $header);
        }

        $this->addSimpleNote(
            $notes,
            $header,
            '/\bDUE\s+TO\s+THE\s+B777-300ERSF\s+CONVERSION,.*?MAINTENANCE\s+WRITE\s+UP\s+NOT\s+(?:\*\s*)*REQUIRED\./is',
            static fn (string $note): string => str_replace(
                ['SYS. THE FOLLOWING', 'LOW. ENGINEERING'],
                ['SYS THE FOLLOWING', 'LOW ENGINEERING'],
                $note,
            ),
        );
        $this->addSimpleNote(
            $notes,
            $header,
            '/\bTIRE\s+PRESSURE\s+INDICATION\s+SYSTEM\s+DEACTIVATED\s+BY\s+EO\s+3249-25-01\..*?TIRE\s+PRESS\s+SYS\b/is',
        );
        $this->addSimpleNote(
            $notes,
            $header,
            '/\bTHIS\s+AIRCRAFT\s+IS\s+PART\s+OF\s+THE\s+OF\s+THE\s+EUROCONTROL\s+DATALINK.*?EUROCONTOL\s+AIRSPACE\b/is',
        );
        $this->addRepetitiveMaintenanceNote($notes, $header);
        $this->addFsaNote($notes, $header);

        usort($notes, static fn (array $left, array $right): int => $left['offset'] <=> $right['offset']);
        $noteTexts = array_values(array_unique(array_column($notes, 'text')));

        return [
            'data' => $noteTexts,
            'source_fragments' => $noteTexts === []
                ? []
                : ['dispatcher_notes' => implode("\n\n", $noteTexts)],
        ];
    }

    private function dispatchHeader(string $text): string
    {
        $start = stripos($text, 'RELEASE TIME');

        if ($start === false) {
            return '';
        }

        $header = substr($text, $start);
        $end = stripos($header, 'MEL/CDL');

        if ($end !== false) {
            $header = substr($header, 0, $end);
        }

        return preg_replace(
            '/KALITTA\s+BRIEF\s+PAGE\s+\d+\s+OF\s+\d+\s+PAGE\s+\d+\s+OF\s+\d+/i',
            ' ',
            $header,
        ) ?? $header;
    }

    /**
     * @param  list<array{offset: int, text: string}>  $notes
     * @param  (callable(string): string)|null  $formatter
     */
    private function addSimpleNote(array &$notes, string $header, string $pattern, ?callable $formatter = null): void
    {
        $matches = [];

        if (preg_match($pattern, $header, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return;
        }

        $note = $this->clean($matches[0][0]);
        $note = $formatter === null ? $note : $formatter($note);

        $notes[] = ['offset' => $matches[0][1], 'text' => $note];
    }

    /** @param list<array{offset: int, text: string}> $notes */
    private function addUnavailableSlotNote(array &$notes, string $header): void
    {
        $matches = [];

        if (preg_match('/\b(?<label>(?:APPROVED\s+)?SLOT\s+TIMES):\s*(?:-\s*)?N\/A\b/i', $header, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return;
        }

        $label = Str::upper(Str::squish($matches['label'][0]));
        $notes[] = [
            'offset' => $matches[0][1],
            'text' => $label === 'SLOT TIMES' ? "SLOT TIMES:\n- N/A" : 'APPROVED SLOT TIMES: N/A',
        ];
    }

    /** @param list<array{offset: int, text: string}> $notes */
    private function addEnrouteSigwxNote(array &$notes, string $header): void
    {
        $matches = [];

        if (preg_match('/\bENROUTE\s+SIGWX\s+NOTATIONS:\s*-\s*(?<notation>.+?)(?=\s*\*{3}|\s*--)/is', $header, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return;
        }

        $notes[] = [
            'offset' => $matches[0][1],
            'text' => "ENROUTE SIGWX NOTATIONS:\n- ".$this->clean($matches['notation'][0]),
        ];
    }

    /** @param list<array{offset: int, text: string}> $notes */
    private function addForecastRunwayNote(array &$notes, string $header): void
    {
        $matches = [];

        if (preg_match(
            '/\bBASED\s+ON\s+FORECAST\s+WINDS:\s+PLANNED\s+TO\s+DEPT\s+RUNWAY:\s*(?<departure>.*?)\s+PLANNED\s+TO\s+ARRV\s+RUNWAY:\s*(?<arrival>.*?)(?=\s*\*{3,})/is',
            $header,
            $matches,
            PREG_OFFSET_CAPTURE,
        ) !== 1) {
            return;
        }

        $arrival = rtrim($this->clean($matches['arrival'][0]), '.');

        if (preg_match('/^(?<runway>\S+)\s+(?<procedure>.+)$/', $arrival, $arrivalParts) === 1) {
            $arrival = $arrivalParts['runway'].'    '.$arrivalParts['procedure'];
        }

        $notes[] = [
            'offset' => $matches[0][1],
            'text' => "BASED ON FORECAST WINDS:\n"
                .'PLANNED TO DEPT RUNWAY: '.$this->clean($matches['departure'][0])."\n"
                .'PLANNED TO ARRV RUNWAY: '.$arrival,
        ];
    }

    /** @param list<array{offset: int, text: string}> $notes */
    private function addRepetitiveMaintenanceNote(array &$notes, string $header): void
    {
        $matches = [];

        if (preg_match(
            '/\bAIRCRAFT\s+REPETITIVE\s+MAINTENANCE\s+ITEMS:\s*(?:\*\s*)*(?<item>.*?)(?=\s*\*{3,})/is',
            $header,
            $matches,
            PREG_OFFSET_CAPTURE,
        ) !== 1) {
            return;
        }

        $item = $this->clean($matches['item'][0]);
        $notes[] = [
            'offset' => $matches[0][1],
            'text' => $item === 'N/A'
                ? 'AIRCRAFT REPETITIVE MAINTENANCE ITEMS: N/A'
                : "AIRCRAFT REPETITIVE MAINTENANCE ITEMS:\n{$item}",
        ];
    }

    /** @param list<array{offset: int, text: string}> $notes */
    private function addFsaNote(array &$notes, string $header): void
    {
        $matches = [];

        if (preg_match('/\bREFER\s+TO\s+FSA\s+PRIOR\s+TO\s+DEPARTURE:\s*(?<reference>\d{2}-\d{2})\b/i', $header, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return;
        }

        $notes[] = [
            'offset' => $matches[0][1],
            'text' => 'REFER TO FSA PRIOR TO DEPARTURE:  '.$matches['reference'][0],
        ];
    }

    private function clean(string $value): string
    {
        return Str::squish(preg_replace('/\s*\*\s*/', ' ', $value) ?? $value);
    }
}
