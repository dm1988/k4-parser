<?php

namespace Tests\Unit\Enums;

use App\Enums\TimeBasis;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ValueError;

class TimeBasisTest extends TestCase
{
    public function test_it_has_expected_cases_and_backed_values(): void
    {
        $this->assertSame('utc', TimeBasis::Utc->value);
        $this->assertSame('local', TimeBasis::Local->value);
    }

    public function test_it_can_be_instantiated_from_valid_value(): void
    {
        $basis = TimeBasis::from('utc');

        $this->assertInstanceOf(TimeBasis::class, $basis);
        $this->assertSame(TimeBasis::Utc, $basis);
    }

    #[DataProvider('invalidValues')]
    public function test_it_returns_null_when_try_from_fails_with_invalid_value(string $value): void
    {
        $this->assertNull(TimeBasis::tryFrom($value));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidValues(): iterable
    {
        yield 'unknown basis' => ['invalid_basis'];
    }

    public function test_it_throws_exception_when_from_fails_with_invalid_value(): void
    {
        $this->expectException(ValueError::class);

        TimeBasis::from('invalid_basis');
    }

    public function test_it_returns_all_enum_values(): void
    {
        $this->assertSame([
            TimeBasis::Utc,
            TimeBasis::Local,
        ], TimeBasis::cases());
    }
}
