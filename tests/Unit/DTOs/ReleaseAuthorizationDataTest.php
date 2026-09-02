<?php

namespace Tests\Unit\DTOs;

use App\DTOs\ReleaseAuthorizationData;
use App\Enums\OperationsSpecification;
use PHPUnit\Framework\TestCase;

class ReleaseAuthorizationDataTest extends TestCase
{
    public function test_it_serializes_the_operations_specification(): void
    {
        $authorization = new ReleaseAuthorizationData(OperationsSpecification::B44);

        $this->assertSame(['operationsSpecification' => 'b44'], $authorization->toArray());
        $this->assertSame($authorization->toArray(), $authorization->jsonSerialize());
        $this->assertTrue((new \ReflectionClass($authorization))->isReadOnly());
    }

    public function test_it_defaults_to_unknown(): void
    {
        $this->assertSame(
            OperationsSpecification::Unknown,
            (new ReleaseAuthorizationData)->operationsSpecification,
        );
    }
}
