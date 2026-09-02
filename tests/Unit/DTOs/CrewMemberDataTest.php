<?php

namespace Tests\Unit\DTOs;

use App\DTOs\CrewMemberData;
use PHPUnit\Framework\TestCase;

class CrewMemberDataTest extends TestCase
{
    public function test_it_serializes_shared_crew_fields(): void
    {
        $member = new CrewMemberData('Alex Morgan', 'CP', 'YIP', '4827', true);

        $this->assertSame([
            'name' => 'Alex Morgan',
            'role' => 'CP',
            'base' => 'YIP',
            'employeeNumber' => '4827',
            'highMins' => true,
        ], $member->toArray());
    }
}
