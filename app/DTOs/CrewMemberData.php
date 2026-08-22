<?php

namespace App\DTOs;

use JsonSerializable;

final readonly class CrewMemberData implements JsonSerializable
{
    public function __construct(
        public string $name,
        public ?string $role = null,
        public ?string $base = null,
        public ?string $employeeNumber = null,
    ) {}

    /** @return array{name: string, role: ?string, base: ?string, employeeNumber: ?string} */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'role' => $this->role,
            'base' => $this->base,
            'employeeNumber' => $this->employeeNumber,
        ];
    }

    /** @return array{name: string, role: ?string, base: ?string, employeeNumber: ?string} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
