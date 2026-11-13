<?php

namespace App\Services\CrewBids;

use App\Exceptions\BidPortalException;
use SensitiveParameter;

class ComparePortalBidDraft
{
    public function __construct(private ReadBidPortalPage $pages) {}

    /**
     * Compare form values only. A Bid Sheet field is not a verified submitted bid.
     *
     * @param  array{maximum_line: int, training: bool, training_lines: list<int>, slots: list<array{rank: int, line_field: string, line: ?int}>}  $sheet
     * @param  list<string>  $selections
     * @return array{added: int, removed: int, reordered: bool}
     */
    public function handle(#[SensitiveParameter] array $sheet, #[SensitiveParameter] array $selections): array
    {
        $replacement = $this->pages->replacement($sheet, $selections);
        $before = [];
        $after = [];

        foreach ($sheet['slots'] as $slot) {
            if ($slot['line'] !== null) {
                $before[] = $slot['line'];
            }

            $value = $replacement[$slot['line_field']];
            if ($value !== '') {
                $after[] = (int) $value;
            }
        }

        if (count($before) !== count(array_unique($before))) {
            throw new BidPortalException('The current Bid Sheet has duplicate line numbers; a safe comparison is unavailable.');
        }

        $keptBefore = array_values(array_filter($before, fn (int $number): bool => in_array($number, $after, true)));
        $keptAfter = array_values(array_filter($after, fn (int $number): bool => in_array($number, $before, true)));

        return [
            'added' => count(array_diff($after, $before)),
            'removed' => count(array_diff($before, $after)),
            'reordered' => $keptBefore !== $keptAfter,
        ];
    }
}
