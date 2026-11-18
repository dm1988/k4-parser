<?php

namespace App\DTOs;

final readonly class CrewBidBrowseResult
{
    /**
     * @param  array<string, array<string, mixed>>  $allLines
     * @param  list<array<string, mixed>>  $lines
     * @param  list<array<string, mixed>>  $selected
     * @param  list<string>  $cities
     * @param  list<string>  $layoverCities
     */
    public function __construct(
        public array $allLines,
        public array $lines,
        public array $selected,
        public array $cities,
        public array $layoverCities,
        public int $total,
    ) {}
}
