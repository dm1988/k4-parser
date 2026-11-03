<?php

namespace App\View\Models;

use App\Services\CrewBids\CalculateCrewBidPay;
use App\Services\CrewBids\CrewPayPeriod;
use App\Services\CrewBids\NormalizeCrewBidPay;

final readonly class CrewBidPayEditorViewModel
{
    /**
     * @param  array<string, string>  $payTypes
     * @param  array<string, string>  $creditModes
     * @param  array<string, string>  $contextChoices
     * @param  list<array<string, mixed>>  $rows
     */
    public function __construct(
        public bool $open,
        public ?string $lineId,
        public string $title,
        public string $period,
        public array $payTypes,
        public array $creditModes,
        public array $contextChoices,
        public array $rows,
        public string $unsupportedHelp,
        public string $longevityLabel,
        public string $checkAirmanLabel,
        public string $lineType,
    ) {}

    /** @param array<string, mixed> $input
     * @param  array<string, mixed>  $personal
     */
    public static function fromInput(bool $open, ?string $lineId, array $input, ?string $periodLabel = null, string $seat = '', array $personal = []): self
    {
        $period = $periodLabel === null ? null : new CrewPayPeriod($periodLabel);
        $rows = $input['rows'] ?? [];

        return new self(
            open: $open,
            lineId: $lineId,
            title: 'Pay adjustments'.($lineId === null ? '' : ' · '.$lineId),
            period: $period === null ? '' : $seat.' · '.$period->start->format('Y-m-d').' through '.$period->end->format('Y-m-d').' UTC compensation period',
            payTypes: NormalizeCrewBidPay::types(),
            creditModes: ['source' => 'Complete linked pairing credits (when coverage permits)', 'manual' => 'Private dated credit scenario (replaces source basis)'],
            contextChoices: [
                'qualified' => 'Use qualified hourly pay',
                'full_period' => 'Available for the full pay period',
                'line_guarantee' => 'Use published line Credit as the pay minimum',
                'unsupported' => 'My circumstances need rules this estimate does not cover',
            ],
            rows: is_array($rows) && array_is_list($rows) ? array_slice($rows, 0, 200) : [],
            unsupportedHelp: 'New-hire weekly pay, partial availability, qualification/crew-class changes, discretionary first-year rates, higher negotiated overrides, sick/jury/emergency leave, hostile-area pay, surface/non-passenger deadhead, 16+ training days, non-flight/training SDFDO pay and special FAR-limit cases require further rules. Flag applicable unsupported pay; do not omit it to obtain a total.',
            longevityLabel: $personal['longevity_date'] ?? 'Not set',
            checkAirmanLabel: array_key_exists('check_airman', $personal) && $personal['check_airman'] === null
                ? 'Profile review required: saved scenarios disagree'
                : (($personal['check_airman'] ?? false) ? 'Minimum $15/h override enabled' : 'Override off'),
            lineType: match ($lineId === null ? null : CalculateCrewBidPay::category($lineId)) {
                'hard' => 'Hard line (F)', 'reserve' => 'Reserve line (Q)', default => 'Line type not recognized',
            },
        );
    }
}
