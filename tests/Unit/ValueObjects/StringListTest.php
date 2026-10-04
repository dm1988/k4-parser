<?php

namespace Tests\Unit\ValueObjects;

use App\ValueObjects\StringList;
use PHPUnit\Framework\TestCase;
use Stringable;

class StringListTest extends TestCase
{
    public function test_it_trims_values_removes_empty_entries_and_reindexes(): void
    {
        $this->assertSame(['A', '0', '12', 'A'], (new StringList(['first' => ' A ', '', null, 0, 12, 'A']))->values);
    }

    public function test_it_keeps_the_existing_string_conversion_contract(): void
    {
        $value = new class implements Stringable
        {
            public function __toString(): string
            {
                return ' crew ';
            }
        };

        $this->assertSame(['crew', '1'], (new StringList([$value, true, false]))->values);
    }

    public function test_non_array_values_produce_an_empty_list(): void
    {
        foreach ([null, 'A', 0, new \stdClass] as $value) {
            $this->assertSame([], (new StringList($value))->values);
        }
    }
}
