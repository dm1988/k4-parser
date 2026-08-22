<?php

namespace Tests\Unit\DTOs;

use App\DTOs\FlightInitData;
use PHPUnit\Framework\TestCase;

class FlightInitDataTest extends TestCase
{
    public function test_it_serializes_the_explicit_acars_init_date(): void
    {
        $data = new FlightInitData(sectionPresent: true, acarsInitDate: '11');

        $this->assertSame([
            'sectionPresent' => true,
            'acarsInitDate' => '11',
        ], $data->toArray());
    }
}
