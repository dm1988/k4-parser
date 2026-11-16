<?php

namespace App\Services\CrewBids;

use App\Exceptions\BidPortalException;
use App\Models\BidPackage;
use Closure;
use SensitiveParameter;

/**
 * @phpstan-type EmployeeReport array{seat: string, selected: ?string, employees: array<string, array{label: string, name: string, seniority: int}>, choices: list<array{rank: int, number: int, local_id: ?string}>, formats: list<string>, checked_at: string, context: array<string, mixed>, download: ?string}
 */
class ViewEmployeeBids
{
    public function __construct(private BidPortalSession $session, private ReadBidPortalPage $sheets, private ReadEmployeeBidPage $pages) {}

    /** @return EmployeeReport */
    public function read(BidPackage $package, #[SensitiveParameter] string $employee, #[SensitiveParameter] string $password, string $seat, ?string $selected = null, ?string $format = null): array
    {
        if (! in_array($seat, ['CA', 'FO'], true) || ($format !== null && ! in_array($format, ['PDF', 'Excel'], true))) {
            throw new BidPortalException('Choose a supported position and report format.');
        }

        return $this->session->viewEmployees($employee, $password, function (string $sheetHtml, Closure $get, Closure $select) use ($package, $employee, $seat, $selected, $format): array {
            $context = $this->context($sheetHtml, $employee);
            if (! $context['account_matches_input'] || $context['period'] !== $package->period || $context['fleet'] !== $package->fleet) {
                throw new BidPortalException('The portal month or fleet does not match this bid package.');
            }
            $page = $this->pages->read($get('/Pages/ViewAllBids.aspx'), $employee, $context['fleet']);
            if ($page['seat'] !== $seat) {
                $page = $this->pages->read($select($this->pages->select($page, $seat, null)), $employee, $context['fleet']);
                if ($page['seat'] !== $seat) {
                    throw new BidPortalException('The portal did not confirm the requested position.');
                }
            }
            $choices = [];
            $formats = [];
            $download = null;
            if ($selected !== null) {
                $fields = $this->pages->select($page, $seat, $selected);
                $expected = $page['employees'][$selected];
                if (count(array_filter($page['employees'], fn (array $candidate): bool => $candidate['name'] === $expected['name'])) !== 1) {
                    throw new BidPortalException('The employee name is ambiguous in the portal directory.');
                }
                $page = $this->pages->read($select($fields), $employee, $context['fleet']);
                if ($page['seat'] !== $seat || $page['selected'] !== $selected || ($page['employees'][$selected] ?? null) !== $expected || $page['report'] === null) {
                    throw new BidPortalException('The selected employee report could not be confirmed.');
                }
                $url = $this->session->destination('https://crewbids.kalittaair.com/Pages/ViewAllBids.aspx', $page['report']);
                $numbers = [];
                $total = null;
                for ($number = 1; $number <= ($total ?? 1); $number++) {
                    $report = $this->pages->report($get($url.'&PageNumber='.$number.'&ZoomMode=Percent&ZoomPct=100&ReloadDocMap=true&SearchStartPage=0'), $expected['name']);
                    if ($report['page'] !== $number || ($total !== null && $report['pages'] !== $total)) {
                        throw new BidPortalException('The employee report pagination changed.');
                    }
                    $total = $report['pages'];
                    foreach ($report['numbers'] as $rank => $line) {
                        if (isset($numbers[$rank]) || in_array($line, $numbers, true)) {
                            throw new BidPortalException('The employee report contains duplicate choices.');
                        }
                        $numbers[$rank] = $line;
                    }
                }
                ksort($numbers);
                if (array_keys($numbers) !== range(1, count($numbers))) {
                    throw new BidPortalException('The employee report is missing choice ranks.');
                }
                $local = [];
                foreach ($package->payload['lines'] ?? [] as $line) {
                    if (preg_match('/\A[FQR]\s*([1-9]\d{0,3})\z/', $line['id'], $match)) {
                        $local[(int) $match[1]][] = $line['id'];
                    }
                }
                foreach ($numbers as $rank => $line) {
                    $matches = $local[$line] ?? [];
                    $choices[] = ['rank' => $rank, 'number' => $line, 'local_id' => count($matches) === 1 ? $matches[0] : null];
                }
                $formats = $page['export'] === null ? [] : $page['formats'];
                if ($format !== null) {
                    if (! in_array($format, $formats, true)) {
                        throw new BidPortalException('This export format is unavailable for the selected report.');
                    }
                    $export = $this->session->destination('https://crewbids.kalittaair.com/Pages/ViewAllBids.aspx', $page['export']);
                    $download = $get($export.rawurlencode($format));
                    $signature = $format === 'PDF' ? '%PDF-' : "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";
                    if (! str_starts_with($download, $signature)) {
                        throw new BidPortalException('The portal did not return a supported report file.');
                    }
                }
            } elseif ($format !== null) {
                throw new BidPortalException('Choose an employee before downloading a report.');
            }
            $after = $this->context($get('/Pages/BidSheet.aspx'), $employee);
            foreach (['account_matches_input', 'period', 'fleet', 'seat', 'package_id', 'package_revision'] as $key) {
                if ($after[$key] !== $context[$key]) {
                    throw new BidPortalException('The portal bid context changed. Load the employee directory again.');
                }
            }

            return ['seat' => $seat, 'selected' => $selected, 'employees' => $page['employees'], 'choices' => $choices,
                'formats' => $formats, 'checked_at' => now()->utc()->format('Y-m-d H:i:s').' UTC', 'context' => $context, 'download' => $download];
        });
    }

    /** @return array{bidders: array<string, array{name: string, seniority: int, numbers: list<int>}>, context: array<string, mixed>, retrieved_at: string, download: ?string} */
    public function all(BidPackage $package, #[SensitiveParameter] string $employee, #[SensitiveParameter] string $password, string $seat, ?string $format = null): array
    {
        if (! in_array($seat, ['CA', 'FO'], true) || ($format !== null && ! in_array($format, ['PDF', 'Excel'], true))) {
            throw new BidPortalException('Choose a supported position and report format.');
        }
        $started = hrtime(true);

        return $this->session->viewEmployees($employee, $password, function (string $html, Closure $get, Closure $select) use ($package, $employee, $seat, $format, $started): array {
            $context = $this->context($html, $employee);
            if (! $context['account_matches_input'] || $context['period'] !== $package->period || $context['fleet'] !== $package->fleet) {
                throw new BidPortalException('The portal month or fleet does not match this bid package.');
            }
            $page = $this->pages->read($get('/Pages/ViewAllBids.aspx'), $employee, $context['fleet']);
            if ($page['seat'] !== $seat) {
                $page = $this->pages->read($select($this->pages->select($page, $seat, null)), $employee, $context['fleet']);
            }
            if ($page['seat'] !== $seat) {
                throw new BidPortalException('The portal did not confirm the requested position.');
            }
            $page = $this->pages->read($select($this->pages->select($page, $seat, 'View All')), $employee, $context['fleet']);
            if (! $page['all_selected'] || $page['seat'] !== $seat || $page['report'] === null || $page['employees'] === []) {
                throw new BidPortalException('The global View All report could not be confirmed.');
            }
            $names = [];
            $seniority = [];
            foreach ($page['employees'] as $id => $person) {
                if (isset($names[$person['name']]) || isset($seniority[$person['seniority']])) {
                    throw new BidPortalException('The global directory has ambiguous names or seniority.');
                }
                $names[$person['name']] = (string) $id;
                $seniority[$person['seniority']] = true;
            }
            $url = $this->session->destination('https://crewbids.kalittaair.com/Pages/ViewAllBids.aspx', $page['report']);
            $groups = [];
            $last = null;
            $total = null;
            $bytes = 0;
            for ($number = 1; $number <= ($total ?? 1); $number++) {
                if (hrtime(true) - $started > 180_000_000_000) {
                    throw new BidPortalException('The global report exceeded the refresh time limit.');
                }
                $content = $get($url.'&PageNumber='.$number.'&ZoomMode=Percent&ZoomPct=100&ReloadDocMap=true&SearchStartPage=0');
                $bytes += strlen($content);
                if ($bytes > 32_000_000) {
                    throw new BidPortalException('The global report is too large.');
                }
                $report = $this->pages->globalReport($content, $names, $last);
                if ($report['page'] !== $number || ($total !== null && $report['pages'] !== $total)) {
                    throw new BidPortalException('The global report pagination changed.');
                }
                $total = $report['pages'];
                foreach ($report['groups'] as $id => $choices) {
                    if (array_key_exists($id, $groups) && (string) $id !== $last) {
                        throw new BidPortalException('The global report repeats an employee across pages.');
                    }
                    $groups[$id] ??= [];
                    foreach ($choices as $rank => $line) {
                        if (isset($groups[$id][$rank])) {
                            throw new BidPortalException('The global report repeats choice rank '.$rank.' on page '.$number.'.');
                        }
                        $groups[$id][$rank] = $line;
                    }
                }
                $last = $report['last'];
            }
            if (count($groups) !== count($page['employees'])) {
                throw new BidPortalException('The global report does not cover the complete employee directory.');
            }
            $bidders = [];
            foreach ($page['employees'] as $id => $person) {
                $choices = $groups[$id];
                ksort($choices);
                if ($choices !== [] && array_keys($choices) !== range(1, count($choices))) {
                    throw new BidPortalException('The global report is missing choice ranks.');
                }
                $bidders[$id] = ['name' => $person['name'], 'seniority' => $person['seniority'], 'numbers' => array_values($choices)];
            }
            $download = null;
            if ($format !== null) {
                if ($page['export'] === null || ! in_array($format, $page['formats'], true)) {
                    throw new BidPortalException('This export format is unavailable for the global report.');
                }
                $export = $this->session->destination('https://crewbids.kalittaair.com/Pages/ViewAllBids.aspx', $page['export']);
                $download = $get($export.rawurlencode($format));
                if (! str_starts_with($download, $format === 'PDF' ? '%PDF-' : "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1")) {
                    throw new BidPortalException('The portal did not return a supported report file.');
                }
            }
            $after = $this->context($get('/Pages/BidSheet.aspx'), $employee);
            foreach (['account_matches_input', 'period', 'fleet', 'seat', 'package_id', 'package_revision'] as $key) {
                if ($after[$key] !== $context[$key]) {
                    throw new BidPortalException('The portal bid context changed. Refresh the global report again.');
                }
            }

            return ['bidders' => $bidders, 'context' => $context, 'retrieved_at' => now()->utc()->toIso8601String(), 'download' => $download];
        });
    }

    /** @return array{account_matches_input: bool, seat: string, fleet: string, period: string, package_id: int, package_revision: int, window_status: string, update_postback: bool} */
    private function context(#[SensitiveParameter] string $html, #[SensitiveParameter] string $employee): array
    {
        return $this->sheets->context($html, $employee);
    }
}
