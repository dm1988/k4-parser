<?php

namespace App\Services\CrewBids;

use App\Exceptions\BidPortalException;
use DOMDocument;
use DOMElement;
use SensitiveParameter;

class ReadBidPortalPage
{
    private const string PREFIX = 'ctl00$ContentPlaceHolder1$';

    /** @return array{action: string, fields: array<string, string>} */
    public function login(#[SensitiveParameter] string $html): array
    {
        $form = $this->form($html);
        $inputs = $this->inputs($form);
        foreach (['Login1$UserName' => 'text', 'Login1$Password' => 'password', 'Login1$LoginButton' => 'submit'] as $name => $type) {
            if (! isset($inputs[$name]) || $inputs[$name]->getAttribute('type') !== $type || $inputs[$name]->hasAttribute('disabled')) {
                throw new BidPortalException('The portal login form is not supported.');
            }
        }
        $fields = $this->hidden($inputs);
        if (($fields['__VIEWSTATE'] ?? '') === '') {
            throw new BidPortalException('The portal login page is missing fresh Web Forms state.');
        }
        $fields['Login1$LoginButton'] = $inputs['Login1$LoginButton']->getAttribute('value');

        return ['action' => $form->getAttribute('action'), 'fields' => $fields];
    }

    /**
     * @return array{action: string, maximum_line: int, training: bool, training_lines: list<int>,
     *   slots: list<array{rank: int, line_field: string, line: ?int, editable: bool}>,
     *   controls: list<string>, submitted_report: bool}
     */
    public function bidSheet(#[SensitiveParameter] string $html): array
    {
        $form = $this->form($html);
        $inputs = $this->inputs($form);
        $hidden = $this->hidden($inputs);
        $maximum = $hidden[self::PREFIX.'hf_lines'] ?? '';
        $training = $hidden[self::PREFIX.'hf_InTrng'] ?? '';
        if (! preg_match('/\A[1-9]\d{0,3}\z/', $maximum) || ! in_array($training, ['True', 'False'], true)
            || ! array_key_exists(self::PREFIX.'hf_BidLines', $hidden) || isset($inputs['Login1$Password'])) {
            throw new BidPortalException('An authenticated, supported Bid Sheet was not found.');
        }
        $trainingLines = [];
        $trainingList = trim($hidden[self::PREFIX.'hf_BidLines']);
        foreach ($trainingList === '' ? [] : explode(',', $trainingList) as $number) {
            if (! preg_match('/\A[1-9]\d{0,3}\z/', trim($number)) || (int) $number > (int) $maximum) {
                throw new BidPortalException('The portal training-line rules are not supported.');
            }
            $trainingLines[] = (int) $number;
        }
        $slots = [];
        $lineCount = 0;
        foreach ($inputs as $name => $choice) {
            if (preg_match('/\A'.preg_quote(self::PREFIX, '/').'tb_l[1-9]\d*\z/', $name)) {
                $lineCount++;
            }
            if (! preg_match('/\A'.preg_quote(self::PREFIX, '/').'tb_c([1-9]\d*)\z/', $name, $match)) {
                continue;
            }
            $lineName = self::PREFIX.'tb_l'.$match[1];
            $line = $inputs[$lineName] ?? null;
            $rank = $choice->getAttribute('value');
            if ($line === null || $choice->getAttribute('type') !== 'text' || $line->getAttribute('type') !== 'text'
                || ! preg_match('/\A[1-9]\d{0,3}\z/', $rank) || isset($slots[(int) $rank])) {
                throw new BidPortalException('The portal choice/rank layout is not supported.');
            }
            $value = trim($line->getAttribute('value'));
            if ($value !== '' && ! preg_match('/\A[1-9]\d{0,3}\z/', $value)) {
                throw new BidPortalException('The existing portal choices are not supported.');
            }
            $slots[(int) $rank] = ['rank' => (int) $rank, 'line_field' => $lineName,
                'line' => $value === '' ? null : (int) $value,
                'editable' => ! $line->hasAttribute('readonly') && ! $line->hasAttribute('disabled')
                    && ! $choice->hasAttribute('disabled') && ! $this->disabledByFieldset($line)];
        }
        ksort($slots);
        if ($slots === [] || count($slots) !== $lineCount || array_keys($slots) !== range(1, count($slots))) {
            throw new BidPortalException('The portal choice/rank layout is incomplete.');
        }
        $controls = [];
        foreach ($inputs as $name => $input) {
            if (in_array($input->getAttribute('type'), ['submit', 'button', 'image'], true) && ! $input->hasAttribute('disabled')) {
                $controls[] = $name;
            }
        }
        foreach ($form->getElementsByTagName('button') as $button) {
            if ($button->getAttribute('name') !== '' && ! $button->hasAttribute('disabled')) {
                $controls[] = $button->getAttribute('name');
            }
        }
        $report = false;
        foreach ($form->getElementsByTagName('a') as $link) {
            if (in_array($link->getAttribute('href'), ['BidLineDataOutput.aspx', './BidLineDataOutput.aspx', '/Pages/BidLineDataOutput.aspx', 'https://crewbids.kalittaair.com/Pages/BidLineDataOutput.aspx'], true)) {
                $report = true;
            }
        }

        return ['action' => $form->getAttribute('action'), 'maximum_line' => (int) $maximum,
            'training' => $training === 'True', 'training_lines' => array_values(array_unique($trainingLines)),
            'slots' => array_values($slots), 'controls' => $controls, 'submitted_report' => $report];
    }

    /**
     * An offline candidate does not establish account/context or permission to submit.
     *
     * @param  array{maximum_line: int, training: bool, training_lines: list<int>, slots: list<array{rank: int, line_field: string}>}  $sheet
     * @param  list<string>  $selections
     * @return array<string, string>
     */
    public function replacement(#[SensitiveParameter] array $sheet, #[SensitiveParameter] array $selections): array
    {
        if ($selections === [] || count($selections) > count($sheet['slots'])) {
            throw new BidPortalException('Choose a nonempty list within the observed portal capacity.');
        }
        $numbers = [];
        foreach ($selections as $id) {
            if (! preg_match('/\A[FQR]\s*([1-9]\d{0,3})\z/', $id, $match)) {
                throw new BidPortalException('The local list contains an unsupported line identifier.');
            }
            $number = (int) $match[1];
            if (isset($numbers[$number])) {
                throw new BidPortalException('The local list contains duplicate portal line numbers.');
            }
            if ($number > $sheet['maximum_line'] || $sheet['training'] !== in_array($number, $sheet['training_lines'], true)) {
                throw new BidPortalException('A local choice is unavailable under the observed portal eligibility rules.');
            }
            $numbers[$number] = $match[1];
        }
        $ordered = array_values($numbers);
        $fields = [];
        foreach ($sheet['slots'] as $slot) {
            $fields[$slot['line_field']] = $ordered[$slot['rank'] - 1] ?? '';
        }

        return $fields;
    }

    private function form(#[SensitiveParameter] string $html): DOMElement
    {
        if (trim($html) === '') {
            throw new BidPortalException('The portal returned an empty page.');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument;
            $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $forms = [];
        foreach ($document->getElementsByTagName('form') as $form) {
            if ($form->getAttribute('id') === 'form1') {
                $forms[] = $form;
            }
        }
        if (count($forms) !== 1 || strtolower($forms[0]->getAttribute('method')) !== 'post') {
            throw new BidPortalException('The portal form is not supported.');
        }

        return $forms[0];
    }

    /**
     * Radio groups share a name; discovery does not serialize these unrelated settings.
     *
     * @return array<string, DOMElement>
     */
    private function inputs(#[SensitiveParameter] DOMElement $form): array
    {
        $inputs = [];
        foreach ($form->getElementsByTagName('input') as $input) {
            $name = $input->getAttribute('name');
            if ($name === '') {
                continue;
            }
            if (isset($inputs[$name])) {
                if (strtolower($inputs[$name]->getAttribute('type')) === 'radio' && strtolower($input->getAttribute('type')) === 'radio') {
                    continue;
                }
                throw new BidPortalException('The portal contains ambiguous form fields.');
            }
            $inputs[$name] = $input;
        }

        return $inputs;
    }

    /** @param array<string, DOMElement> $inputs
     * @return array<string, string>
     */
    private function hidden(#[SensitiveParameter] array $inputs): array
    {
        $fields = [];
        foreach ($inputs as $name => $input) {
            if ($input->getAttribute('type') === 'hidden' && ! $input->hasAttribute('disabled')) {
                $fields[$name] = $input->getAttribute('value');
            }
        }

        return $fields;
    }

    private function disabledByFieldset(#[SensitiveParameter] DOMElement $input): bool
    {
        for ($parent = $input->parentNode; $parent instanceof DOMElement; $parent = $parent->parentNode) {
            if ($parent->tagName === 'fieldset' && $parent->hasAttribute('disabled')) {
                return true;
            }
        }

        return false;
    }
}
