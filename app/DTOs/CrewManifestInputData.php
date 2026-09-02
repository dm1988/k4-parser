<?php

namespace App\DTOs;

final readonly class CrewManifestInputData
{
    /**
     * @param  list<array{name: string, role: ?string, base: ?string, employee_number?: ?string, high_mins?: bool}>  $members
     */
    public function __construct(public array $members) {}
}
