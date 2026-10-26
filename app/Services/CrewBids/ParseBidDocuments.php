<?php

namespace App\Services\CrewBids;

use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Smalot\PdfParser\Parser;
use Throwable;

class ParseBidDocuments
{
    public function __construct(private ReadBidOverview $overview, private Parser $pdf) {}

    /** @param list<UploadedFile> $files
     * @return array{period: string, fleet: string, source_revision: string, fingerprint: string, payload: array<string, mixed>}
     */
    public function parse(array $files): array
    {
        $overview = null;
        $documents = [];
        $hashes = [];
        foreach ($files as $file) {
            $hashes[] = hash_file('sha256', $file->getPathname());
            if (strtolower($file->getClientOriginalExtension()) === 'xlsx') {
                if ($overview !== null) {
                    $this->invalid('Import only one Overview workbook per package.');
                }
                $overview = $this->overview->read($file->getPathname());

                continue;
            }
            try {
                $text = str_replace("\0", '', $this->pdf->parseFile($file->getPathname())->getText());
            } catch (Throwable) {
                $this->invalid('A PDF could not be read. Use the original text-based Lines or Trips PDF.');
            }
            $text = str_replace("\r", '', $text);
            $kind = preg_match('/^Trip Report\b/m', $text) ? 'trips' : (preg_match('/^Lines\s*$/m', $text) ? 'lines' : null);
            if ($kind === null || isset($documents[$kind])) {
                $this->invalid('Import at most one Lines PDF and one Trips PDF; other PDF types are not supported.');
            }
            $documents[$kind] = $text;
        }
        if ($overview === null && ! isset($documents['lines'])) {
            $this->invalid('Import an Overview workbook or a Lines PDF to identify the package.');
        }
        $context = null;
        foreach ($documents as $text) {
            if (! preg_match('/Plan:\s*(\d{4})(\d{2})\s*\/\s*(747|777)\s*\/\s*([^\n]+)/', $text, $match)) {
                $this->invalid('The PDF package identity could not be established.');
            }
            $next = ['period' => $match[1].'-'.$match[2], 'fleet' => $match[3], 'source_revision' => trim($match[4])];
            if (($context !== null && $context !== $next) || ($overview !== null && ($overview['period'] !== $next['period'] || $overview['fleet'] !== $next['fleet']))) {
                $this->invalid('The files belong to different months, fleets, or source revisions.');
            }
            $context = $next;
        }
        $context ??= ['period' => $overview['period'], 'fleet' => $overview['fleet'], 'source_revision' => 'Overview'];
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $context['period'])) {
            $this->invalid('The package period is invalid.');
        }
        $lines = $overview['lines'] ?? [];
        if (isset($documents['lines'])) {
            preg_match_all('/^([FQR]\d+)\s*-\s*(\d+)\/(\d+)\s*\n(.*?)(?=^Printed |^[FQR]\d+\s*-|\z)/ms', $documents['lines'], $matches, PREG_SET_ORDER);
            if ($matches === []) {
                $this->invalid('No line records could be read from the Lines PDF.');
            }
            foreach ($matches as $match) {
                $id = $match[1];
                preg_match('/Credit time:\s*(\d+:\d{2})/i', $match[4], $credit);
                preg_match('/Duty days:\s*(\d+)/i', $match[4], $duty);
                preg_match('/Days Off:\s*(\d+)/i', $match[4], $off);
                preg_match('/RSV Base:\s*([A-Z]{3})/', $match[4], $base);
                if (isset($lines[$id]) && ($lines[$id]['captains'] !== (int) $match[2] || $lines[$id]['first_officers'] !== (int) $match[3] || $lines[$id]['credit'] !== $this->overview->credit($credit[1] ?? ''))) {
                    $this->invalid('Lines and Overview disagree on crew or credit for '.$id.'. Review the source revision.');
                }
                $lines[$id] ??= ['id' => $id, 'type' => $id[0] === 'F' ? 'flying' : 'reserve', 'captains' => (int) $match[2], 'first_officers' => (int) $match[3], 'crew' => $match[2].' CA '.$match[3].' FO', 'credit' => $this->overview->credit($credit[1] ?? ''), 'pairings' => [], 'calendar' => [], 'start_city' => null, 'carry_in' => null];
                $lines[$id]['duty_days'] = isset($duty[1]) ? (int) $duty[1] : null;
                $lines[$id]['source_days_off'] = isset($off[1]) ? (int) $off[1] : null;
                if (isset($base[1])) {
                    $lines[$id]['start_city'] = $base[1];
                }
                $lines[$id]['source_page'] = 'Lines '.$id;
            }
        }
        $trips = isset($documents['trips']) ? $this->trips($documents['trips'], $context['period']) : [];
        $month = CarbonImmutable::parse($context['period'].'-01', 'UTC');
        foreach ($lines as &$line) {
            for ($day = 1; $day <= $month->daysInMonth; $day++) {
                $date = $month->addDays($day - 1)->format('Y-m-d');
                $line['calendar'][$date] ??= ['status' => 'unknown', 'activity' => 'Unknown'];
            }
            ksort($line['calendar']);
            foreach ($line['pairings'] as $pairing) {
                $trip = $trips[$pairing['id']] ?? null;
                if ($trip === null) {
                    continue;
                }
                $line['start_city'] ??= $trip['start_city'];
                foreach ($line['calendar'] as $date => &$entry) {
                    if ($date >= $trip['start_date'] && $date <= $trip['end_date']) {
                        $entry = ['status' => 'occupied', 'activity' => 'Trip '.$trip['id'].' (includes layover/rest)'];
                    }
                }
                unset($entry);
            }
        }
        unset($line);
        sort($hashes);

        return $context + ['fingerprint' => hash('sha256', implode('', $hashes)), 'payload' => ['lines' => $lines, 'trips' => $trips, 'coverage' => ['overview' => $overview !== null, 'lines' => isset($documents['lines']), 'trips' => isset($documents['trips'])]]];
    }

    /** @return array<string, array<string, mixed>> */
    private function trips(string $text, string $period): array
    {
        $text = preg_replace('/^(?:Trip Report|Planning Period:.*|Rule Set Name:.*|Plan:.*|Printed .*|Day Flight.*|  Duty Summary.*)\n?/m', '', $text);
        $chunks = preg_split('/(?=^Trip Id:)/m', $text) ?: [];
        $trips = [];
        foreach ($chunks as $chunk) {
            if (! preg_match('/Trip Id:\s*(\d+)/', $chunk, $id)) {
                continue;
            }
            preg_match_all('/^(\d{2}[A-Za-z]{3})\s+[A-Za-z]{2}\s+([^\n]*?)([A-Z]{3})-([A-Z]{3})(\d{2}:\d{2})(\d{2}:\d{2})/m', $chunk, $legs, PREG_SET_ORDER);
            if ($legs === []) {
                continue;
            }
            $normalized = [];
            foreach ($legs as $leg) {
                $date = CarbonImmutable::createFromFormat('!dMY', $leg[1].substr($period, 0, 4), 'UTC');
                if ($date === null) {
                    continue;
                }
                $month = CarbonImmutable::parse($period.'-01', 'UTC');
                if ($date->lessThan($month->subMonths(1))) {
                    $date = $date->addYear();
                } elseif ($date->greaterThan($month->addMonths(2))) {
                    $date = $date->subYear();
                }
                $normalized[] = ['date' => $date->format('Y-m-d'), 'origin' => $leg[3], 'destination' => $leg[4], 'departure' => $leg[5], 'arrival' => $leg[6], 'deadhead' => str_contains($leg[2], 'DH')];
            }
            if ($normalized === []) {
                continue;
            }
            $first = $normalized[0];
            $last = $normalized[array_key_last($normalized)];
            $end = CarbonImmutable::parse($last['date'].' '.$last['arrival'], 'UTC');
            if ($last['arrival'] < $last['departure']) {
                $end = $end->addDay();
            }
            preg_match_all('/Debriefing\s+(\d{2}:\d{2})/', $chunk, $debrief);
            $release = $debrief[1][array_key_last($debrief[1])] ?? $last['arrival'];
            if ($release < $last['arrival']) {
                $end = $end->addDay();
            }
            preg_match('/Show Time\s+(\d{2}:\d{2})/', $chunk, $show);
            $start = CarbonImmutable::parse($first['date'], 'UTC');
            if (($show[1] ?? $first['departure']) > $first['departure']) {
                $start = $start->subDay();
            }
            $trips[$id[1]] = ['id' => $id[1], 'start_date' => $start->format('Y-m-d'), 'end_date' => $end->format('Y-m-d'), 'start_city' => $first['origin'], 'end_city' => $last['destination'], 'report' => $show[1] ?? null, 'release' => $release, 'legs' => $normalized];
        }

        return $trips;
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['files' => $message]);
    }
}
