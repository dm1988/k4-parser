<?php

namespace App\Services\CrewBids;

use App\Exceptions\BidPortalException;
use DOMDocument;
use DOMElement;
use DOMXPath;
use SensitiveParameter;

/**
 * @phpstan-type EmployeePage array{seat: string, selected: ?string, all_selected: bool, can_view_all: bool, employees: array<string, array{label: string, name: string, seniority: int}>, fields: array<string, string>, targets: array<string, string>, report: ?string, export: ?string, formats: list<string>}
 */
class ReadEmployeeBidPage
{
    private const string PREFIX = 'ctl00$ContentPlaceHolder1$';

    /** @return EmployeePage */
    public function read(#[SensitiveParameter] string $html, #[SensitiveParameter] string $employee, string $fleet): array
    {
        $xpath = $this->document($html);
        $form = $this->one($xpath, '//form[@id="form1"]');
        if (strtolower($form->getAttribute('method')) !== 'post'
            || ! in_array($form->getAttribute('action'), ['ViewAllBids.aspx', './ViewAllBids.aspx', '/Pages/ViewAllBids.aspx', 'https://crewbids.kalittaair.com/Pages/ViewAllBids.aspx'], true)
            || $this->text($this->one($xpath, '//*[@id="LoginName1"]')) !== $employee
            || $this->text($this->one($xpath, '//*[@id="ContentPlaceHolder1_lbl_Type"]')) !== $fleet) {
            throw new BidPortalException('The employee viewer account or fleet could not be verified.');
        }
        $fields = [];
        foreach (['__EVENTTARGET', '__EVENTARGUMENT', '__LASTFOCUS', '__VIEWSTATE', '__VIEWSTATEGENERATOR', '__EVENTVALIDATION'] as $name) {
            $nodes = $xpath->query('.//input[@name="'.$name.'"]', $form);
            if ($nodes->length > 1) {
                throw new BidPortalException('The employee viewer has ambiguous form state.');
            }
            if ($nodes->length === 1) {
                $input = $nodes->item(0);
                if (! $input instanceof DOMElement || $input->getAttribute('type') !== 'hidden' || $input->hasAttribute('disabled')) {
                    throw new BidPortalException('The employee viewer form state is unsupported.');
                }
                $fields[$name] = $input->getAttribute('value');
            }
        }
        if (($fields['__VIEWSTATE'] ?? '') === '' || ($fields['__EVENTVALIDATION'] ?? '') === '') {
            throw new BidPortalException('The employee viewer is missing fresh Web Forms state.');
        }
        $counter = $xpath->query('.//input[@name="ctl00$tb_usersLoggedIn"]', $form);
        if ($counter->length === 1 && $counter->item(0) instanceof DOMElement) {
            $fields['ctl00$tb_usersLoggedIn'] = $counter->item(0)->getAttribute('value');
        }
        $seat = '';
        $targets = [];
        foreach (['CA' => 0, 'FO' => 1] as $position => $index) {
            $radio = $this->one($xpath, './/input[@id="ContentPlaceHolder1_RadioButtonList1_'.$index.'"]', $form);
            if ($radio->getAttribute('name') !== self::PREFIX.'RadioButtonList1' || $radio->getAttribute('value') !== $position
                || $radio->getAttribute('type') !== 'radio') {
                throw new BidPortalException('The employee position controls are unsupported.');
            }
            if ($radio->hasAttribute('checked')) {
                if ($seat !== '') {
                    throw new BidPortalException('The employee position is ambiguous.');
                }
                $seat = $position;
            } elseif (! $radio->hasAttribute('disabled')) {
                $targets[$position] = self::PREFIX.'RadioButtonList1$'.$index;
            }
        }
        if ($seat === '') {
            foreach ($xpath->query('.//input[@id="ContentPlaceHolder1_RadioButtonList1_2"]', $form) as $radio) {
                if ($radio instanceof DOMElement && $radio->hasAttribute('checked') && $radio->getAttribute('name') === self::PREFIX.'RadioButtonList1' && $radio->getAttribute('value') === 'FE') {
                    $seat = 'FE';
                }
            }
        }
        if ($seat === '') {
            throw new BidPortalException('The employee position was not selected.');
        }
        $select = $this->one($xpath, './/select[@id="ContentPlaceHolder1_ddl_crewMembers"]', $form);
        if ($select->getAttribute('name') !== self::PREFIX.'ddl_crewMembers' || $select->hasAttribute('disabled') || $select->hasAttribute('multiple')) {
            throw new BidPortalException('The employee selector is unsupported.');
        }
        $employees = [];
        $selected = null;
        $selectedCount = 0;
        $canViewAll = false;
        foreach ($select->getElementsByTagName('option') as $option) {
            $value = $option->getAttribute('value');
            if ($option->hasAttribute('selected')) {
                $selectedCount++;
                $selected = $value;
            }
            if (in_array($value, ['Select Name', 'View All'], true)) {
                $canViewAll = $canViewAll || ($value === 'View All' && ! $option->hasAttribute('disabled'));

                continue;
            }
            $label = $this->text($option);
            if (! preg_match('/\A\d{1,20}\z/', $value) || isset($employees[$value]) || $option->hasAttribute('disabled')
                || ! preg_match('/\A([1-9]\d{0,6})\s*-\s*(.+)\z/u', $label, $match) || mb_strlen($label) > 200) {
                throw new BidPortalException('The employee directory layout is unsupported.');
            }
            $employees[$value] = ['label' => $label, 'name' => $match[2], 'seniority' => (int) $match[1]];
        }
        if ($selectedCount > 1 || ($selected !== null && ! in_array($selected, ['Select Name', 'View All'], true) && ! isset($employees[$selected]))) {
            throw new BidPortalException('The selected employee could not be verified.');
        }
        $fields[self::PREFIX.'ddl_crewMembers'] = $selected ?? 'Select Name';
        $fields[self::PREFIX.'RadioButtonList1'] = $seat;
        [$report, $export, $formats] = $this->reportControls($xpath, $form);

        return ['seat' => $seat, 'selected' => isset($employees[$selected ?? '']) ? $selected : null, 'all_selected' => $selected === 'View All', 'can_view_all' => $canViewAll,
            'employees' => $employees, 'fields' => $fields, 'targets' => $targets,
            'report' => $report, 'export' => $export, 'formats' => $formats];
    }

    /**
     * @param  EmployeePage  $page
     * @return array<string, string>
     */
    public function select(#[SensitiveParameter] array $page, string $seat, ?string $employee): array
    {
        if (! in_array($seat, ['CA', 'FO'], true)) {
            throw new BidPortalException('Choose CA or FO.');
        }
        $fields = $page['fields'];
        if ($seat !== $page['seat']) {
            $target = $page['targets'][$seat] ?? null;
        } else {
            if ($employee === null || ($employee === 'View All' ? ! $page['can_view_all'] : ! isset($page['employees'][$employee]))) {
                throw new BidPortalException('Choose an employee from the fresh portal directory.');
            }
            $target = self::PREFIX.'ddl_crewMembers';
            $fields[self::PREFIX.'ddl_crewMembers'] = $employee;
        }
        if ($target === null) {
            throw new BidPortalException('The requested position is unavailable.');
        }

        return array_replace($fields, [self::PREFIX.'RadioButtonList1' => $seat, '__EVENTTARGET' => $target, '__EVENTARGUMENT' => '']);
    }

    /** @return array{numbers: array<int, int>, page: int, pages: int} */
    public function report(#[SensitiveParameter] string $html, string $name): array
    {
        $xpath = $this->document($html);
        $container = $this->one($xpath, '//*[@id="oReportDiv"]');
        $table = $this->one($xpath, './/table[@id="t_3"]', $container);
        $headers = false;
        $identity = false;
        $choices = [];
        foreach ($this->reportRows($xpath, $table) as $cells) {
            if ($cells === ['', '', '']) {
                continue;
            }
            if ($cells === ['Name', 'Choice', 'Line'] && ! $headers) {
                $headers = true;

                continue;
            }
            if ($cells === [$name, '', ''] && $headers && ! $identity) {
                $identity = true;

                continue;
            }
            if (! $identity || count($cells) !== 3 || $cells[0] !== ''
                || ! preg_match('/\A[1-9]\d{0,2}\z/', $cells[1]) || ! preg_match('/\A[1-9]\d{0,3}\z/', $cells[2])
                || isset($choices[(int) $cells[1]]) || (int) $cells[1] > 315) {
                throw new BidPortalException('The employee report identity or choice layout could not be verified.');
            }
            $choices[(int) $cells[1]] = (int) $cells[2];
        }
        $pagination = [];
        $title = 0;
        foreach ($xpath->query('.//td[not(.//table)]', $container) as $cell) {
            $text = $this->text($cell);
            if ($text === 'Current Bid Status For') {
                $title++;
            }
            if (preg_match('/\A([1-9]\d*) of ([1-9]\d*)\z/', $text, $match)) {
                $pagination[] = [(int) $match[1], (int) $match[2]];
            }
        }
        if (! $identity || $title !== 1 || $choices === [] || count($pagination) !== 1 || $pagination[0][0] > $pagination[0][1] || $pagination[0][1] > 20) {
            throw new BidPortalException('A complete employee report is unavailable.');
        }
        ksort($choices);

        return ['numbers' => $choices, 'page' => $pagination[0][0], 'pages' => $pagination[0][1]];
    }

    /**
     * Report groups can continue across page boundaries without repeating the name.
     *
     * @param  array<string, string>  $names  Employee name to directory identifier
     * @return array{groups: array<string, array<int, int>>, last: ?string, page: int, pages: int}
     */
    public function globalReport(#[SensitiveParameter] string $html, array $names, ?string $previous = null): array
    {
        $xpath = $this->document($html);
        $container = $this->one($xpath, '//*[@id="oReportDiv"]');
        $table = $this->one($xpath, './/table[@id="t_3"]', $container);
        $groups = [];
        $current = $previous;
        $headers = false;
        foreach ($this->reportRows($xpath, $table) as $cells) {
            if ($cells === ['', '', '']) {
                continue;
            }
            if ($cells === ['Name', 'Choice', 'Line'] && ! $headers) {
                $headers = true;

                continue;
            }
            if (! $headers || count($cells) !== 3) {
                throw new BidPortalException('The global employee report columns are unsupported.');
            }
            if ($cells[0] !== '' && $cells[1] === '' && $cells[2] === '') {
                if (! isset($names[$cells[0]])) {
                    throw new BidPortalException('A global report employee is missing or ambiguous in the directory.');
                }
                $current = $names[$cells[0]];
                if (array_key_exists($current, $groups)) {
                    throw new BidPortalException('The global report repeats an employee group.');
                }
                $groups[$current] = [];

                continue;
            }
            if ($current === null || $cells[0] !== '' || ! preg_match('/\A[1-9]\d{0,2}\z/', $cells[1])
                || ! preg_match('/\A[1-9]\d{0,3}\z/', $cells[2]) || (int) $cells[1] > 315) {
                throw new BidPortalException('The global employee choice layout is unsupported.');
            }
            $rank = (int) $cells[1];
            if (isset($groups[$current][$rank])) {
                throw new BidPortalException('The global employee report repeats a choice rank.');
            }
            $groups[$current][$rank] = (int) $cells[2];
        }
        $pagination = [];
        $titles = 0;
        foreach ($xpath->query('.//td[not(.//table)]', $container) as $cell) {
            $text = $this->text($cell);
            $titles += $text === 'Current Bid Status For' ? 1 : 0;
            if (preg_match('/\A([1-9]\d*) of ([1-9]\d*)\z/', $text, $match)) {
                $pagination[] = [(int) $match[1], (int) $match[2]];
            }
        }
        if (! $headers || count($pagination) !== 1 || ($pagination[0][0] === 1 ? $titles !== 1 : $titles > 1)
            || $pagination[0][0] > $pagination[0][1] || $pagination[0][1] > 200 || $groups === []) {
            throw new BidPortalException('The global employee report is incomplete or too large.');
        }

        return ['groups' => $groups, 'last' => $current, 'page' => $pagination[0][0], 'pages' => $pagination[0][1]];
    }

    /** @return list<list<string>> */
    private function reportRows(DOMXPath $xpath, DOMElement $table): array
    {
        $rows = [];
        foreach ($xpath->query('./tbody/tr | ./tr', $table) as $row) {
            $cells = [];
            foreach ($xpath->query('./td', $row) as $cell) {
                $cells[] = $this->text($cell);
            }
            $rows[] = $cells;
        }

        return $rows;
    }

    /** @return array{?string, ?string, list<string>} */
    private function reportControls(DOMXPath $xpath, DOMElement $form): array
    {
        $report = [];
        $export = [];
        foreach ($form->getElementsByTagName('script') as $script) {
            preg_match_all('~["\'](/Reserved\.ReportViewerWebControl\.axd\?[^"\'<>\s]+)["\']~', $script->textContent, $matches);
            foreach ($matches[1] as $url) {
                parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
                if (($query['Controller'] ?? '') === 'ContentPlaceHolder1_ReportViewer1' && ($query['OpType'] ?? '') === 'ReportArea') {
                    $report[] = $url;
                }
                if (($query['OpType'] ?? '') === 'Export' && ($query['FileName'] ?? '') === 'ViewAllBids' && ($query['Format'] ?? null) === '') {
                    $export[] = $url;
                }
            }
        }
        $report = array_values(array_unique($report));
        $export = array_values(array_unique($export));
        if (count($report) > 1 || count($export) > 1) {
            throw new BidPortalException('The employee report destinations are ambiguous.');
        }
        if ($report !== [] && $export !== []) {
            parse_str((string) parse_url($report[0], PHP_URL_QUERY), $readQuery);
            parse_str((string) parse_url($export[0], PHP_URL_QUERY), $exportQuery);
            if (! is_string($readQuery['ReportID'] ?? null) || $readQuery['ReportID'] === ''
                || ! is_string($exportQuery['ReportID'] ?? null) || $exportQuery['ReportID'] === '') {
                throw new BidPortalException('The employee report session is missing.');
            }
            foreach (['ControlID', 'ReportStack'] as $key) {
                if (! is_string($readQuery[$key] ?? null) || $readQuery[$key] === '' || ($exportQuery[$key] ?? null) !== $readQuery[$key]) {
                    throw new BidPortalException('The employee export belongs to an unverified report.');
                }
            }
        }
        $formats = [];
        foreach ($xpath->query('.//select[@id="ContentPlaceHolder1_ReportViewer1_ctl01_ctl05_ctl00"]/option', $form) as $option) {
            if ($option instanceof DOMElement && ! $option->hasAttribute('disabled') && in_array($option->getAttribute('value'), ['PDF', 'Excel'], true)) {
                $formats[] = $option->getAttribute('value');
            }
        }

        return [$report[0] ?? null, $export[0] ?? null, array_values(array_unique($formats))];
    }

    private function document(#[SensitiveParameter] string $html): DOMXPath
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument;
            if (trim($html) === '' || ! $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
                throw new BidPortalException('The employee report page could not be read.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return new DOMXPath($document);
    }

    private function one(DOMXPath $xpath, string $query, ?DOMElement $context = null): DOMElement
    {
        $nodes = $xpath->query($query, $context);
        if ($nodes->length !== 1 || ! $nodes->item(0) instanceof DOMElement) {
            throw new BidPortalException('The employee viewer layout is missing or ambiguous.');
        }

        return $nodes->item(0);
    }

    private function text(\DOMNode $element): string
    {
        return trim(preg_replace('/[\s\x{00a0}]+/u', ' ', $element->textContent) ?? '');
    }
}
