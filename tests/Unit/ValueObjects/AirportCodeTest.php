<?php

namespace Tests\Unit\ValueObjects;

use App\ValueObjects\AirportCode;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AirportCodeTest extends TestCase
{
    public function test_it_normalizes_and_represents_an_iata_code(): void
    {
        $code = new AirportCode(' jfk ');

        $this->assertSame('JFK', $code->value);
        $this->assertSame('JFK', (string) $code);
        $this->assertTrue($code->isIata());
        $this->assertFalse($code->isIcao());
    }

    public function test_it_normalizes_and_represents_an_icao_code(): void
    {
        $code = AirportCode::from(' kjfk ');

        $this->assertSame('KJFK', $code->value);
        $this->assertFalse($code->isIata());
        $this->assertTrue($code->isIcao());
    }

    #[DataProvider('invalidCodes')]
    public function test_it_rejects_invalid_airport_codes(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Airport code must be a three-letter IATA code or four-letter ICAO code.',
        );

        new AirportCode($value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidCodes(): iterable
    {
        yield 'empty' => [''];
        yield 'too short' => ['AB'];
        yield 'too long' => ['ABCDE'];
        yield 'contains a number' => ['AB1'];
        yield 'contains whitespace' => ['J FK'];
        yield 'contains non-ASCII letters' => ['ÉÉÉ'];
    }

    public function test_it_compares_normalized_codes_by_value(): void
    {
        $code = new AirportCode('KJFK');

        $this->assertTrue($code->equals(new AirportCode(' kjfk ')));
        $this->assertFalse($code->equals(new AirportCode('KLAX')));
    }

    public function test_it_serializes_to_its_normalized_value(): void
    {
        $code = new AirportCode('kjfk');

        $this->assertSame('"KJFK"', json_encode($code, JSON_THROW_ON_ERROR));
    }
}
