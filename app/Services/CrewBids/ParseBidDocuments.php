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
        $lineBodies = [];
        if (isset($documents['lines'])) {
            preg_match_all('/^([FQR]\d+)\s*-\s*(\d+)\/(\d+)\s*\n(.*?)(?=^Printed |^[FQR]\d+\s*-|\z)/ms', $documents['lines'], $matches, PREG_SET_ORDER);
            if ($matches === []) {
                $this->invalid('No line records could be read from the Lines PDF.');
            }
            foreach ($matches as $match) {
                $id = $match[1];
                $lineBodies[$id] = $match[4];
                preg_match('/Credit time:\s*(\d+:\d{2})/i', $match[4], $credit);
                preg_match('/Block time:\s*(\d+:\d{2})/i', $match[4], $block);
                preg_match('/Duty days:\s*(\d+)/i', $match[4], $duty);
                preg_match('/Days Off:\s*(\d+)/i', $match[4], $off);
                preg_match('/RSV Base:\s*([A-Z]{3})/', $match[4], $base);
                if (isset($lines[$id]) && ($lines[$id]['captains'] !== (int) $match[2] || $lines[$id]['first_officers'] !== (int) $match[3] || $lines[$id]['credit'] !== $this->overview->credit($credit[1] ?? ''))) {
                    $this->invalid('Lines and Overview disagree on crew or credit for '.$id.'. Review the source revision.');
                }
                $lines[$id] ??= ['id' => $id, 'type' => $id[0] === 'F' ? 'flying' : 'reserve', 'captains' => (int) $match[2], 'first_officers' => (int) $match[3], 'crew' => $match[2].' CA '.$match[3].' FO', 'credit' => $this->overview->credit($credit[1] ?? ''), 'pairings' => [], 'calendar' => [], 'start_city' => null, 'carry_in' => null];
                $lines[$id]['duty_days'] = isset($duty[1]) ? (int) $duty[1] : null;
                $lines[$id]['block'] = $this->overview->credit($block[1] ?? '');
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
            if ($overview === null && $line['type'] === 'flying') {
                preg_match_all('/^(\d{3,4})\h*$(?=\n(?:Sun|Mon|Tue|Wed|Thu|Fri|Sat)\b|\n*\z)/m', $lineBodies[$line['id']] ?? '', $pairingIds);
                foreach (array_unique($pairingIds[1]) as $id) {
                    $line['pairings'][] = ['id' => $id, 'date' => $trips[$id]['start_date'] ?? null];
                }
            }
            $line = $this->applyTripTotals($line, $trips, $month->addMonth());
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

        return $context + ['fingerprint' => hash('sha256', 'crew-bids-parser:v3:'.implode('', $hashes)), 'payload' => ['lines' => $lines, 'trips' => $trips, 'coverage' => ['overview' => $overview !== null, 'lines' => isset($documents['lines']), 'trips' => isset($documents['trips'])]]];
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  array<string, array<string, mixed>>  $trips
     * @return array<string, mixed>
     */
    private function applyTripTotals(array $line, array $trips, CarbonImmutable $nextMonth): array
    {
        $line['source_credit'] = $line['credit'];
        $line['source_block'] = $line['block'] ?? null;
        $line['block'] ??= null;
        $line['credit_basis'] = 'line_source';
        $line['block_basis'] = 'line_source';
        $line['base_credit'] = $line['credit'];
        $line['credit_seconds'] = $line['credit'] === null ? null : $line['credit'] * 60;
        $line['carry_flying_seconds'] = null;
        $line['carry_credit_seconds'] = null;
        $line['carry_deadhead_credit_seconds'] = null;
        $line['carry_premium_seconds'] = null;
        $line['deadhead_credit_seconds'] = null;
        $ids = array_unique(array_column($line['pairings'], 'id'));
        if ($ids === []) {
            return $line;
        }
        foreach (['credit', 'block'] as $field) {
            $values = [];
            foreach ($ids as $id) {
                $values[] = $trips[$id][$field.'_minutes'] ?? null;
            }
            if (! in_array(null, $values, true)) {
                $line[$field] = array_sum($values);
                $line[$field.'_basis'] = 'trip_totals';
            }
        }
        $line['base_credit'] = $line['credit'];
        $line['credit_seconds'] = $line['credit'] === null ? null : $line['credit'] * 60;
        if ($line['credit_basis'] === 'trip_totals') {
            $carrySeconds = 0;
            $carryDeadheadSeconds = 0;
            $deadheadSeconds = 0;
            $allLegsAvailable = true;
            foreach ($ids as $id) {
                $trip = $trips[$id];
                if ($trip['legs'] === []) {
                    if ($trip['end_date'] >= $nextMonth->format('Y-m-d')) {
                        return $line;
                    }
                    $allLegsAvailable = false;
                }
                foreach ($trip['legs'] as $leg) {
                    $departure = CarbonImmutable::parse($leg['date'].' '.$leg['departure'], 'UTC');
                    $arrival = CarbonImmutable::parse($leg['date'].' '.$leg['arrival'], 'UTC');
                    if ($leg['arrival'] < $leg['departure']) {
                        $arrival = $arrival->addDay();
                    }
                    if ($leg['deadhead']) {
                        $creditSeconds = intdiv($arrival->getTimestamp() - $departure->getTimestamp(), 2);
                        $deadheadSeconds += $creditSeconds;
                        if ($departure >= $nextMonth) {
                            $carryDeadheadSeconds += $creditSeconds;
                        }

                        continue;
                    }
                    if (str_starts_with($leg['flight'] ?? '', 'GT ')) {
                        continue;
                    }
                    if ($departure >= $nextMonth) {
                        $carrySeconds += $arrival->getTimestamp() - $departure->getTimestamp();
                    }
                }
            }
            $line['carry_flying_seconds'] = $carrySeconds;
            $line['carry_deadhead_credit_seconds'] = $carryDeadheadSeconds;
            $line['carry_credit_seconds'] = $carrySeconds + $carryDeadheadSeconds;
            $line['deadhead_credit_seconds'] = $allLegsAvailable ? $deadheadSeconds : null;
            $line['carry_premium_seconds'] = intdiv($line['carry_credit_seconds'], 2);
            $line['credit_seconds'] += $line['carry_premium_seconds'];
            $line['credit'] = intdiv($line['credit_seconds'], 60);
        }

        return $line;
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
            preg_match_all('/^(\d{2}[A-Za-z]{3})\s+[A-Za-z]{2}\s+([^\n]*?)([A-Z]{3})-([A-Z]{3})(\d{2}:\d{2})(\d{2}:\d{2})\h*(\d{1,3}:\d{2})?[^\n]*/m', $chunk, $legs, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
            if ($legs === []) {
                continue;
            }
            $normalized = [];
            foreach ($legs as $index => $capturedLeg) {
                $leg = array_column($capturedLeg, 0);
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
                $segmentEnd = $legs[$index + 1][0][1] ?? strlen($chunk);
                $segment = substr($chunk, $capturedLeg[0][1], $segmentEnd - $capturedLeg[0][1]);
                preg_match('/\bRest\h*(\d{1,3}:\d{2})/', $segment, $rest);
                $normalized[] = ['date' => $date->format('Y-m-d'), 'origin' => $leg[3], 'destination' => $leg[4], 'departure' => $leg[5], 'arrival' => $leg[6], 'deadhead' => str_contains($leg[2], 'DH'), 'flight' => trim($leg[2]), 'block' => ($leg[7] ?? '') !== '' ? $leg[7] : null, 'rest_after' => $rest[1] ?? null];
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
            preg_match('/Duty Days:\h*(\d+)/', $chunk, $dutyDays);
            preg_match('/Crew:\h*(\d+)\/(\d+)/', $chunk, $crew);
            preg_match('/Block Time:\h*(\d+:\d{2})/i', $chunk, $blockTime);
            preg_match('/Credit Time:\h*(\d+:\d{2})/i', $chunk, $creditTime);
            $start = CarbonImmutable::parse($first['date'], 'UTC');
            if (($show[1] ?? $first['departure']) > $first['departure']) {
                $start = $start->subDay();
            }
            $trips[$id[1]] = ['id' => $id[1], 'start_date' => $start->format('Y-m-d'), 'end_date' => $end->format('Y-m-d'), 'start_city' => $first['origin'], 'end_city' => $last['destination'], 'report' => $show[1] ?? null, 'release' => $release, 'duty_days' => isset($dutyDays[1]) ? (int) $dutyDays[1] : null, 'crew_label' => isset($crew[1], $crew[2]) ? $crew[1].' CA, '.$crew[2].' FO' : null, 'block_minutes' => $this->overview->credit($blockTime[1] ?? ''), 'credit_minutes' => $this->overview->credit($creditTime[1] ?? ''), 'legs' => $normalized];
        }

        return $trips;
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['files' => $message]);
    }
}
