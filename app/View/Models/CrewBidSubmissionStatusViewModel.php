<?php

namespace App\View\Models;

use App\DTOs\SubmittedBidOrder;

final readonly class CrewBidSubmissionStatusViewModel
{
    public function __construct(
        public string $state,
        public string $label,
        public string $description,
    ) {}

    /** @param list<string> $selections */
    public static function fromOrder(SubmittedBidOrder $order, array $selections): self
    {
        if (! $order->available()) {
            return new self('unconfirmed', 'Kalitta: unconfirmed', 'The app has no confirmed Kalitta report for this package and seat. Saving locally does not submit your bid.');
        }
        $checkedAt = $order->checkedAt?->utc()->format('Y-m-d H:i:s').' UTC';
        if (array_keys($order->ranks) === $selections) {
            return new self('matched', 'Kalitta: matched', 'This list matches the Kalitta report checked '.$checkedAt.'. Later portal changes are not checked automatically.');
        }

        return new self('changed', 'Kalitta: list differs', 'This list differs from the Kalitta report checked '.$checkedAt.'. Review your current order before submitting it to Kalitta.');
    }
}
