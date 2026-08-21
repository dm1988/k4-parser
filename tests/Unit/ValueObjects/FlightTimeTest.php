<?php

namespace Tests\Unit\ValueObjects;

use App\ValueObjects\FlightTime;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class FlightTimeTest extends TestCase
{
    public function test_it_represents_a_local_time_with_an_explicit_timezone(): void
    {
        $time = FlightTime::local('2026-06-15T19:45:00', 'America/Anchorage');

        $this->assertSame('America/Anchorage', $time->timezone);
        $this->assertSame('2026-06-15T19:45:00-08:00', $time->toIso8601String());
        $this->assertFalse($time->isUtc());
    }

    public function test_it_represents_and_normalizes_a_utc_time(): void
    {
        $time = FlightTime::utc('2026-06-16T03:45:00+00:00');

        $this->assertSame('UTC', $time->timezone);
        $this->assertSame('2026-06-16T03:45:00+00:00', $time->toIso8601String());
        $this->assertTrue($time->isUtc());
    }

    public function test_it_converts_an_instant_without_mutating_timezone_context(): void
    {
        $local = FlightTime::from('2026-06-15T19:45:00', 'America/Anchorage');
        $utc = $local->toUtc();

        $this->assertSame('America/Anchorage', $local->timezone);
        $this->assertSame('2026-06-15T19:45:00-08:00', $local->toIso8601String());
        $this->assertSame('UTC', $utc->timezone);
        $this->assertSame('2026-06-16T03:45:00+00:00', $utc->toIso8601String());
        $this->assertTrue($local->sameInstantAs($utc));
        $this->assertFalse($local->equals($utc));
    }

    public function test_it_accepts_a_datetime_instance_and_applies_the_requested_context(): void
    {
        $instant = CarbonImmutable::parse('2026-06-16T03:45:00+00:00');
        $time = new FlightTime($instant, 'Asia/Tokyo');

        $this->assertSame('Asia/Tokyo', $time->timezone);
        $this->assertSame('2026-06-16T12:45:00+09:00', $time->toIso8601String());
    }

    public function test_it_compares_both_the_instant_and_timezone_context(): void
    {
        $first = new FlightTime('2026-06-16T12:45:00', 'Asia/Tokyo');
        $same = new FlightTime('2026-06-16T12:45:00', 'Asia/Tokyo');
        $different = new FlightTime('2026-06-16T13:45:00', 'Asia/Tokyo');

        $this->assertTrue($first->equals($same));
        $this->assertFalse($first->equals($different));
    }

    public function test_it_round_trips_with_its_timezone_context(): void
    {
        $time = new FlightTime('2026-06-16T12:45:00', 'Asia/Tokyo');

        $restored = FlightTime::fromArray($time->toArray());

        $this->assertTrue($time->equals($restored));
        $this->assertSame(
            '{"instant":"2026-06-16T03:45:00+00:00","value":"2026-06-16T12:45:00+09:00","basis":"local","timezone":"Asia\/Tokyo"}',
            json_encode($time, JSON_THROW_ON_ERROR),
        );
    }

    public function test_it_rejects_an_empty_time_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Flight time value must be a complete ISO-8601 UTC date and time.');

        new FlightTime('', 'UTC');
    }

    public function test_it_rejects_an_invalid_time_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Flight time value must be a complete ISO-8601 UTC date and time.');

        new FlightTime('not-a-time', 'UTC');
    }

    public function test_it_rejects_a_utc_value_without_an_explicit_utc_offset(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Flight time value must be a complete ISO-8601 UTC date and time.');

        FlightTime::utc('2026-06-16T03:45:00');
    }

    public function test_it_rejects_an_offset_bearing_local_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Flight time value must be a valid date and time.');

        FlightTime::local('2026-06-15T19:45:00-08:00', 'America/Anchorage');
    }

    public function test_it_rejects_a_nonexistent_daylight_saving_time(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Flight time value is ambiguous or does not exist in the requested timezone.');

        FlightTime::local('2026-03-08T02:30:00', 'America/New_York');
    }

    public function test_it_rejects_an_ambiguous_daylight_saving_time(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Flight time value is ambiguous or does not exist in the requested timezone.');

        FlightTime::local('2026-11-01T01:30:00', 'America/New_York');
    }

    public function test_it_rejects_an_empty_timezone(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Flight time timezone must be a valid timezone identifier.');

        new FlightTime('2026-06-16T03:45:00+00:00', '');
    }

    public function test_it_rejects_an_invalid_timezone(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Flight time timezone must be a valid timezone identifier.');

        new FlightTime('2026-06-16T03:45:00+00:00', 'Not/A_Timezone');
    }

    public function test_it_rejects_a_fixed_offset_as_local_timezone_context(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Flight time timezone must be a valid timezone identifier.');

        FlightTime::local('2026-06-15T19:45:00', '-08:00');
    }
}
