<?php

namespace App\Services\CrewBids;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use SimpleXMLElement;
use ZipArchive;

class ReadBidOverview
{
    /** @return array{period: string, fleet: string, lines: array<string, array<string, mixed>>} */
    public function read(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            $this->invalid('The Overview must be a readable .xlsx workbook.');
        }
        try {
            $workbook = $this->xml($zip, 'xl/workbook.xml');
            if ((string) ($workbook->xpath('//*[local-name()="workbookPr"]')[0]['date1904'] ?? '') === '1') {
                $this->invalid('The Overview uses an unsupported date system.');
            }
            $sheet = $workbook->xpath('//*[local-name()="sheet"]')[0] ?? null;
            if ($sheet === null || ! preg_match('/\b(747|777)\s+Overview\b/i', (string) $sheet['name'], $fleetMatch)) {
                $this->invalid('Select a 747 or 777 Overview workbook.');
            }
            $strings = [];
            if ($zip->locateName('xl/sharedStrings.xml') !== false) {
                foreach ($this->xml($zip, 'xl/sharedStrings.xml')->xpath('//*[local-name()="si"]') ?: [] as $item) {
                    $strings[] = implode('', array_map(strval(...), $item->xpath('.//*[local-name()="t"]') ?: []));
                }
            }
            $relationships = $this->xml($zip, 'xl/_rels/workbook.xml.rels');
            $relationshipId = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            $target = null;
            foreach ($relationships->children() as $relationship) {
                if ((string) $relationship['Id'] === $relationshipId && (string) $relationship['TargetMode'] !== 'External') {
                    $target = (string) $relationship['Target'];
                }
            }
            if ($target === null || str_contains($target, '..')) {
                $this->invalid('The Overview worksheet relationship is invalid.');
            }
            $sheetPath = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.$target;
            $rows = [];
            foreach ($this->xml($zip, $sheetPath)->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') ?: [] as $row) {
                $cells = [];
                foreach ($row->children() as $cell) {
                    $column = preg_replace('/\d/', '', (string) $cell['r']);
                    $value = (string) $cell->v;
                    $cells[$column] = trim(match ((string) $cell['t']) {
                        's' => $strings[(int) $value] ?? '',
                        'inlineStr' => implode('', array_map(strval(...), $cell->xpath('.//*[local-name()="t"]') ?: [])),
                        default => $value,
                    });
                }
                $rows[] = $cells;
            }

            return $this->normalize($rows, $fleetMatch[1]);
        } finally {
            $zip->close();
        }
    }

    /** @param list<array<string, string>> $rows
     * @return array{period: string, fleet: string, lines: array<string, array<string, mixed>>}
     */
    private function normalize(array $rows, string $fleet): array
    {
        $header = array_shift($rows) ?? [];
        if (($header['A'] ?? '') !== 'Line Name' || ($header['B'] ?? '') !== 'Crew Complement' || ($header['C'] ?? '') !== 'Credit') {
            $this->invalid('The Overview headers are not supported.');
        }
        $dates = [];
        foreach ($header as $column => $value) {
            if (ctype_digit($value) && (int) $value >= 40000 && (int) $value <= 80000) {
                $dates[$column] = CarbonImmutable::parse('1899-12-30', 'UTC')->addDays((int) $value)->format('Y-m-d');
            }
        }
        if (count($dates) < 28 || substr(reset($dates), 8) !== '01') {
            $this->invalid('The Overview needs dated daily columns beginning on the first of the month.');
        }
        $period = substr(reset($dates), 0, 7);
        $month = CarbonImmutable::parse($period.'-01', 'UTC');
        $lines = [];
        foreach ($rows as $row) {
            $id = $row['A'] ?? '';
            if ($id === '' || $id === 'Line Name') {
                continue;
            }
            if (! preg_match('/^[FQR]\d{1,4}$/', $id) || isset($lines[$id])) {
                $this->invalid('The Overview contains an unsupported or duplicate line ID.');
            }
            if (! preg_match('/^(?:(\d+) CA (\d+) FO|(\d+) FO ONLY)$/', $row['B'] ?? '', $crew)) {
                $this->invalid('The Overview contains an unsupported crew complement.');
            }
            $reserve = $id[0] !== 'F';
            $calendar = [];
            $pairings = [];
            $dateColumns = $dates;
            if ($reserve) {
                /** Reserve sections omit the three summary columns; their date cells start at D. */
                $dateColumns = [];
                for ($day = 1; $day <= $month->daysInMonth; $day++) {
                    $dateColumns[$this->column($day + 3)] = $month->addDays($day - 1)->format('Y-m-d');
                }
            }
            foreach ($dateColumns as $column => $date) {
                $value = $row[$column] ?? '';
                $status = match (true) {
                    $value === '' => 'off',
                    strtolower($value) === 'x', ctype_digit($value), in_array(strtoupper($value), ['RSV', 'TRN', 'DH'], true) => 'occupied',
                    default => 'unknown',
                };
                $calendar[$date] = ['status' => $status, 'activity' => $status === 'occupied' ? ($reserve ? 'Reserve' : 'Trip / occupied') : ucfirst($status)];
                if (! $reserve && ctype_digit($value)) {
                    $pairings[] = ['id' => $value, 'date' => $date];
                }
            }
            for ($day = 1; $day <= $month->daysInMonth; $day++) {
                $date = $month->addDays($day - 1)->format('Y-m-d');
                $calendar[$date] ??= ['status' => 'unknown', 'activity' => 'Unknown'];
            }
            ksort($calendar);
            $lines[$id] = [
                'id' => $id, 'type' => $reserve ? 'reserve' : 'flying',
                'captains' => (int) $crew[1], 'first_officers' => (int) ($crew[2] !== '' ? $crew[2] : $crew[3]),
                'crew' => $row['B'], 'credit' => $this->credit($row['C'] ?? ''),
                'calendar' => $calendar, 'pairings' => $pairings, 'start_city' => null,
                'duty_days' => ! $reserve && ctype_digit($row['D'] ?? '') ? (int) $row['D'] : null,
                'carry_in' => ! $reserve && ctype_digit($row['F'] ?? '') ? (int) $row['F'] : null,
            ];
        }
        if ($lines === []) {
            $this->invalid('The Overview contains no supported lines.');
        }

        return ['period' => $period, 'fleet' => $fleet, 'lines' => $lines];
    }

    public function credit(string $value): ?int
    {
        return preg_match('/^(\d{1,3})[:+](\d{2})$/', trim($value), $match) && (int) $match[2] < 60
            ? (int) $match[1] * 60 + (int) $match[2] : null;
    }

    private function column(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + $number % 26).$name;
            $number = intdiv($number, 26);
        }

        return $name;
    }

    private function xml(ZipArchive $zip, string $name): SimpleXMLElement
    {
        $stat = $zip->statName($name);
        if ($stat === false || $stat['size'] > 8_000_000) {
            $this->invalid('The Overview has missing or oversized worksheet data.');
        }
        $data = $zip->getFromName($name);
        if ($data === false || stripos($data, '<!DOCTYPE') !== false || stripos($data, '<!ENTITY') !== false) {
            $this->invalid('The Overview XML is invalid.');
        }
        $xml = @simplexml_load_string($data, SimpleXMLElement::class, LIBXML_NONET);
        if ($xml === false) {
            $this->invalid('The Overview XML could not be read.');
        }

        return $xml;
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['files' => $message]);
    }
}
