<?php

namespace App\View\Models;

final readonly class CrewBidLineViewModel
{
    /**
     * @param  list<array{label: string, value: string, title: ?string, containerClasses: string, labelClasses: string, valueClasses: string}>  $summaryMetrics
     * @param  list<array{label: string, description: string, icon: string, classes: string, showLabelOnMobile: bool}>  $quickFlags
     * @param  list<array{date: string, activity: string, description: string, label: string, classes: string}>  $calendarDays
     * @param  list<array<string, mixed>>  $trips
     * @param  list<array{label: string, value: string, valueClasses: string}>  $detailMetrics
     * @param  list<string>  $creditNotes
     * @param  list<string>  $warnings
     * @param  list<string>  $matchDetails
     * @param  list<string>  $filterDetails
     */
    public function __construct(
        public string $id,
        public string $typeLabel,
        public string $headingClasses,
        public ?string $crew,
        public ?int $rank,
        public array $summaryMetrics,
        public array $quickFlags,
        public array $calendarDays,
        public ?int $carryDays,
        public array $trips,
        public string $tripSummaryLabel,
        public bool $singleTrip,
        public array $detailMetrics,
        public array $creditNotes,
        public array $warnings,
        public string $warningSummary,
        public array $matchDetails,
        public array $filterDetails,
    ) {}

    /** @param array<string, mixed> $line */
    public static function fromLine(array $line, ?int $rank = null, bool $showBlocks = false): self
    {
        $reserve = $line['type'] === 'reserve';
        $trips = $line['trip_details'];
        $tripCount = count($trips);

        return new self(
            id: $line['id'],
            typeLabel: $reserve ? 'Reserve' : 'Flying',
            headingClasses: $reserve ? 'text-orange-700 dark:text-orange-300' : 'text-slate-900 dark:text-slate-100',
            crew: filled($line['crew']) ? $line['crew'] : null,
            rank: $rank,
            summaryMetrics: self::summaryMetrics($line),
            quickFlags: self::quickFlags($line['quick_flags']),
            calendarDays: self::calendarDays($line['month'], $reserve),
            carryDays: $line['carry_days'],
            trips: $trips,
            tripSummaryLabel: $tripCount === 1 ? 'Trip '.$trips[0]['id'] : 'Trips ('.$tripCount.')',
            singleTrip: $tripCount === 1,
            detailMetrics: self::detailMetrics($line, $showBlocks),
            creditNotes: self::creditNotes($line),
            warnings: $line['warnings'],
            warningSummary: implode(' · ', $line['warnings']),
            matchDetails: $line['match_details'],
            filterDetails: $line['filter_details'],
        );
    }

    /**
     * @param  array<string, mixed>  $line
     * @return list<array{label: string, value: string, title: ?string, containerClasses: string, labelClasses: string, valueClasses: string}>
     */
    private static function summaryMetrics(array $line): array
    {
        $metrics = [];
        if (isset($line['credit'])) {
            $metrics[] = ['label' => 'Credit', 'value' => $line['credit_label'], 'title' => $line['credit_label'].' · adjusted credit, h:mm[:ss]', 'containerClasses' => 'min-w-0', 'labelClasses' => '', 'valueClasses' => 'truncate font-mono text-xs font-semibold'];
        }
        if (isset($line['block'])) {
            $metrics[] = ['label' => 'Block', 'value' => $line['block_label'], 'title' => $line['block_label'].' · block, h:mm', 'containerClasses' => 'min-w-0', 'labelClasses' => '', 'valueClasses' => 'truncate font-mono text-xs font-semibold'];
        }
        if (isset($line['days_off'])) {
            $metrics[] = ['label' => 'Days off', 'value' => (string) $line['days_off'], 'title' => null, 'containerClasses' => 'shrink-0', 'labelClasses' => 'whitespace-nowrap', 'valueClasses' => 'font-mono text-xs font-semibold'];
        }

        return $metrics;
    }

    /**
     * @param  list<array{type: 'return'|'route'|'mixed'|'deadhead', label: string, description: string}>  $flags
     * @return list<array{label: string, description: string, icon: string, classes: string, showLabelOnMobile: bool}>
     */
    private static function quickFlags(array $flags): array
    {
        return array_map(static fn (array $flag): array => [
            'label' => $flag['label'],
            'description' => $flag['description'],
            'icon' => match ($flag['type']) {
                'return' => 'heroicon-o-arrow-path',
                'route' => 'heroicon-o-arrow-right',
                'mixed' => 'heroicon-o-arrows-right-left',
                'deadhead' => 'heroicon-o-ticket',
            },
            'classes' => match ($flag['type']) {
                'return' => 'bg-blue-50 text-[#1B365D] dark:bg-blue-950 dark:text-blue-200',
                'deadhead' => 'bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
                'route', 'mixed' => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
            },
            'showLabelOnMobile' => $flag['type'] === 'deadhead',
        ], $flags);
    }

    /**
     * @param  array<string, array{status: 'occupied'|'off'|'unknown', activity: string}>  $month
     * @return list<array{date: string, activity: string, description: string, label: string, classes: string}>
     */
    private static function calendarDays(array $month, bool $reserve): array
    {
        $days = [];
        foreach ($month as $date => $entry) {
            $day = substr($date, 8);
            $unknown = $entry['status'] === 'unknown';
            $days[] = [
                'date' => $date,
                'activity' => $entry['activity'],
                'description' => $date.' UTC · '.$entry['activity'],
                'label' => $unknown ? '?' : $day,
                'classes' => match ($entry['status']) {
                    'occupied' => $reserve ? 'bg-orange-600 text-white dark:bg-orange-400 dark:text-slate-950' : 'bg-[#1B365D] text-white dark:bg-blue-300 dark:text-slate-950',
                    'off' => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
                    'unknown' => 'bg-amber-200 text-amber-950',
                },
            ];
        }

        return $days;
    }

    /**
     * @param  array<string, mixed>  $line
     * @return list<array{label: string, value: string, valueClasses: string}>
     */
    private static function detailMetrics(array $line, bool $showBlocks): array
    {
        $metrics = [];
        foreach (['blocks' => 'Work blocks', 'duty_days' => 'Duty days (source)', 'start_city' => 'Start city', 'crew' => 'Crew complement', 'carry_days' => 'Carry-in days after month-end'] as $key => $label) {
            if (($key !== 'blocks' || $showBlocks) && filled($line[$key] ?? null)) {
                $metrics[] = ['label' => $label, 'value' => (string) $line[$key], 'valueClasses' => $key === 'crew' ? '' : 'font-mono'];
            }
        }
        foreach ([
            'source_credit_label' => 'Lines / Overview credit · h:mm',
            'source_block_label' => 'Lines block · h:mm',
            'base_credit_label' => 'Base credit · h:mm',
            'deadhead_credit_label' => 'Deadhead credit · 50% elapsed · h:mm[:ss]',
            'carry_credit_label' => 'Carry credit subtotal · before premium · h:mm[:ss]',
            'carry_flying_label' => 'Carry-in flying · h:mm[:ss]',
            'carry_deadhead_credit_label' => 'Carry-in deadhead credit · h:mm[:ss]',
            'carry_premium_label' => 'Extra 50% carry-in credit · h:mm[:ss]',
        ] as $key => $label) {
            if ($line[$key] !== 'Not available') {
                $metrics[] = ['label' => $label, 'value' => $line[$key], 'valueClasses' => 'font-mono'];
            }
        }

        return $metrics;
    }

    /**
     * @param  array<string, mixed>  $line
     * @return list<string>
     */
    private static function creditNotes(array $line): array
    {
        if (! isset($line['credit']) && ! isset($line['block'])) {
            return [];
        }
        $notes = [];
        if (isset($line['credit'])) {
            $notes[] = ($line['credit_basis'] ?? '') === 'trip_totals'
                ? 'Base credit: Complete pairing Credit Time totals from Trips, including credited deadheads.'
                : 'Base credit: Recorded line credit.';
        }
        if (isset($line['block'])) {
            $notes[] = ($line['block_basis'] ?? '') === 'trip_totals'
                ? 'Block: Complete pairing Block Time totals from Trips.'
                : 'Block: Recorded Lines block time.';
        }
        if (isset($line['carry_credit_seconds'])) {
            $notes[] = 'Carry credit subtotal counts legs departing in the next calendar month in UTC: flying at 100% and deadheads at 50%. Flights departing before month-end and ground transfers are excluded from this subtotal. Adjusted credit currently adds 50% of the subtotal to base credit. Seconds preserve fractional minutes. This subtotal is not total contractual Pay Credit Hours (PCH), which also covers other qualifying activities such as reserve, vacation, and training.';
        }

        return $notes;
    }
}
