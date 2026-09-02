<?php

namespace App\Services\FlightPlan;

use App\DTOs\CrewManifestInputData;
use App\DTOs\CrewMemberData;

class CrewMemberDataBuilder
{
    public function __construct(
        private readonly FlightInitFieldNormalizer $flightInitFieldNormalizer,
    ) {}

    /**
     * @return list<CrewMemberData>
     */
    public function fromExtracted(CrewManifestInputData $source): array
    {
        return $this->build($source->members, 'employee_number', 'high_mins');
    }

    /** @return list<CrewMemberData> */
    public function fromSerialized(mixed $source): array
    {
        return is_array($source) ? $this->build($source, 'employeeNumber', 'highMins') : [];
    }

    /**
     * @param  array<array-key, mixed>  $source
     * @return list<CrewMemberData>
     */
    private function build(array $source, string $employeeNumberKey, string $highMinsKey): array
    {
        $crewMembers = [];

        foreach ($source as $member) {
            if (! is_array($member)) {
                continue;
            }

            $name = $this->nullableString($member['name'] ?? null);

            if ($name === null) {
                continue;
            }

            $crewMembers[] = new CrewMemberData(
                name: $name,
                role: $this->nullableString($member['role'] ?? null),
                base: $this->nullableString($member['base'] ?? null),
                employeeNumber: $this->flightInitFieldNormalizer->employeeNumber($member[$employeeNumberKey] ?? null),
                highMins: ($member[$highMinsKey] ?? false) === true,
            );
        }

        return $crewMembers;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value !== '' ? $value : null;
    }
}
