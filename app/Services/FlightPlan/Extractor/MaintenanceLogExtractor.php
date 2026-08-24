<?php

namespace App\Services\FlightPlan\Extractor;

use App\Enums\EtopsApplicability;
use App\Enums\MaintenanceItemType;
use App\Exceptions\FlightPlanDataConflictException;
use Illuminate\Support\Str;

class MaintenanceLogExtractor
{
    private const FIELD_LABELS = 'DESCRIPTION|DESC|STATUS|DMI|REFERENCE|REF|LIMITATION|LIMITATIONS|PROCEDURE|PROCEDURES';

    private const MEL_CDL_NUMBER_PATTERN = '\d{2}-\d{2}-[A-Z0-9]{1,4}(?:-[A-Z0-9]{1,4}){0,2}';

    /**
     * @return array{
     *     data: array{section_present: bool, etops_applicability: string, items: list<array{type: string, number: string, description: string, reference: ?string, status: ?string, limitations: ?string, procedures: ?string}>},
     *     source_fragments: array<string, string>
     * }
     */
    public function extract(string $text): array
    {
        $maintenanceSection = $this->maintenanceSection($text);
        $items = $maintenanceSection === null ? [] : $this->items($maintenanceSection['body']);
        $sourceFragments = array_filter([
            'maintenance_log' => $maintenanceSection['source'] ?? null,
        ], static fn (?string $value): bool => $value !== null);

        foreach ($items as $index => $item) {
            $sourceFragments['maintenance_item_'.($index + 1)] = $item['source'];
        }

        return [
            'data' => [
                'section_present' => $maintenanceSection !== null,
                'etops_applicability' => $this->etopsApplicability($text)->value,
                'items' => array_map(
                    static fn (array $item): array => $item['data'],
                    $items,
                ),
            ],
            'source_fragments' => $sourceFragments,
        ];
    }

    public function etopsApplicability(string $text): EtopsApplicability
    {
        $explicit = [];

        if (preg_match('/\bETOPS(?:\h+FLIGHT)?\h*[:=-]\h*(YES|Y|NO|N)\b/i', $text, $explicit) === 1) {
            return in_array(Str::upper($explicit[1]), ['YES', 'Y'], true)
                ? EtopsApplicability::ConfirmedEtops
                : EtopsApplicability::ConfirmedNonEtops;
        }

        if (preg_match('/\bNO\h+ETOPS\b/i', $text) === 1) {
            return EtopsApplicability::ConfirmedNonEtops;
        }

        if (preg_match('/\bETOPS\h+(?:\d{2,3}\h*\/\h*)?\d{2,3}\b/i', $text) === 1) {
            return EtopsApplicability::ConfirmedEtops;
        }

        return EtopsApplicability::Unknown;
    }

    /** @return array{body: string, source: string}|null */
    private function maintenanceSection(string $text): ?array
    {
        $headerPattern = preg_match('/\bMEL\h*\/\h*CDL(?:\h*\/\h*DMI)?/i', $text) === 1
            ? 'MEL\h*\/\h*CDL(?:\h*\/\h*DMI)?'
            : 'MAINTENANCE(?:\h+LOG|\h+ITEMS?)';
        $pattern = '/(?:'.$headerPattern.')\h*:?\h*'
            .'(?<body>.*?)(?=\bEND\h+MAINTENANCE\h+LOG\b|\bPASSED\h+RAIM\h+REQUIREMENTS\b|(?:\R\h*(?:CREW|FUEL\h+SUMMARY|ROUTE|NOTAMS?|WEATHER)\b)|\z)/is';
        $matches = [];

        if (preg_match($pattern, $text, $matches) !== 1) {
            return null;
        }

        return [
            'body' => trim($matches['body']),
            'source' => Str::squish($matches[0]),
        ];
    }

    /**
     * @return list<array{data: array{type: string, number: string, description: string, reference: ?string, status: ?string, limitations: ?string, procedures: ?string}, source: string}>
     */
    private function items(string $section): array
    {
        $pattern = '/(?:\A|\R|\bITEM\h+)(?<type>MEL|CDL|DMI)\h+'
            .'(?:(?:ITEM|NO\.?)\h*)?(?<number>[A-Z0-9][A-Z0-9.-]*)'
            .'|(?<marker>[MC])\s+(?<operational_number>'.self::MEL_CDL_NUMBER_PATTERN.')'
            .'(?=\s*DMI\h+\d{6,}\b)/i';
        $matches = [];

        if (preg_match_all($pattern, $section, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL) === false) {
            return [];
        }

        $items = [];

        foreach ($matches as $index => $match) {
            $typeValue = $match['type'][0] ?? null;
            $marker = $match['marker'][0] ?? null;
            $type = is_string($typeValue)
                ? MaintenanceItemType::tryFrom(Str::upper($typeValue))
                : match ($marker) {
                    'M', 'm' => MaintenanceItemType::Mel,
                    'C', 'c' => MaintenanceItemType::Cdl,
                    default => null,
                };
            $numberValue = $match['number'][0] ?? $match['operational_number'][0] ?? null;
            $number = is_string($numberValue) ? Str::upper(rtrim($numberValue, '.')) : '';

            if ($type === null || $number === '' || ! $this->validNumber($type, $number)) {
                continue;
            }

            $start = $match[0][1];
            $end = $matches[$index + 1][0][1] ?? strlen($section);
            $source = Str::squish(substr($section, $start, $end - $start));
            $reference = $this->reference($source);
            $description = $this->fieldValue($source, ['DESCRIPTION', 'DESC'])
                ?? $this->operationalDescription($source, $type, $number, $reference);

            if ($description === null) {
                continue;
            }

            $data = [
                'type' => $type->value,
                'number' => $number,
                'description' => $description,
                'reference' => $reference,
                'status' => $this->upperFieldValue($source, ['STATUS']),
                'limitations' => $this->fieldValue($source, ['LIMITATION', 'LIMITATIONS']),
                'procedures' => $this->fieldValue($source, ['PROCEDURE', 'PROCEDURES']),
            ];
            $key = $type->value.'|'.$number.'|'.($reference ?? '');

            if (isset($items[$key]) && $items[$key]['data'] !== $data) {
                throw FlightPlanDataConflictException::forField("maintenance item {$type->value} {$number}");
            }

            $items[$key] = [
                'data' => $data,
                'source' => $source,
            ];
        }

        return array_values($items);
    }

    private function reference(string $source): ?string
    {
        $reference = $this->fieldValue($source, ['DMI', 'REFERENCE', 'REF']);

        if ($reference !== null) {
            return $reference;
        }

        $matches = [];

        if (preg_match('/DMI\h+([A-Z0-9][A-Z0-9-]*)\b/i', $source, $matches) !== 1) {
            return null;
        }

        return Str::upper($matches[1]);
    }

    private function operationalDescription(
        string $source,
        MaintenanceItemType $type,
        string $number,
        ?string $reference,
    ): ?string {
        $description = preg_replace(
            '/^(?:'.preg_quote($type->value, '/').'|[MC])\h*'.preg_quote($number, '/').'/i',
            '',
            $source,
        );

        if (! is_string($description)) {
            return null;
        }

        if ($reference !== null) {
            $description = preg_replace(
                '/^\h*DMI\h*:?\h*'.preg_quote($reference, '/').'/i',
                '',
                $description,
            ) ?? $description;
        }

        $description = preg_replace(
            '/\b(?:KALITTA\h+BRIEF\h+)?PAGE\h+\d+\h+OF\h+\d+\b/i',
            ' ',
            $description,
        ) ?? $description;
        $description = trim(Str::squish($description), " \t\n\r\0\x0B|;");

        return $description !== '' ? $description : null;
    }

    private function validNumber(MaintenanceItemType $type, string $number): bool
    {
        return match ($type) {
            MaintenanceItemType::Mel,
            MaintenanceItemType::Cdl => preg_match('/^'.self::MEL_CDL_NUMBER_PATTERN.'$/', $number) === 1,
            MaintenanceItemType::Dmi => preg_match('/^[A-Z0-9]{2,}(?:-[A-Z0-9]+)*$/', $number) === 1,
        };
    }

    /** @param list<string> $labels */
    private function fieldValue(string $source, array $labels): ?string
    {
        $labelPattern = implode('|', array_map(
            static fn (string $label): string => preg_quote($label, '/'),
            $labels,
        ));
        $matches = [];
        $pattern = '/\b(?:'.$labelPattern.')\h*:\h*(.*?)'
            .'(?=\h*(?:\||;)\h*\b(?:'.self::FIELD_LABELS.')\h*:|\h+\b(?:'.self::FIELD_LABELS.')\h*:|\z)/is';

        if (preg_match($pattern, $source, $matches) !== 1) {
            return null;
        }

        $value = trim(Str::squish($matches[1]), " \t\n\r\0\x0B|;");

        return $value !== '' ? $value : null;
    }

    /** @param list<string> $labels */
    private function upperFieldValue(string $source, array $labels): ?string
    {
        $value = $this->fieldValue($source, $labels);

        return $value === null ? null : Str::upper($value);
    }
}
