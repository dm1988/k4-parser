<?php

namespace Tests\Unit;

use App\Enums\DutyEventType;
use App\Enums\ScheduleEventType;
use App\Services\Schedule\DutyTypeResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DutyTypeResolverTest extends TestCase
{
    #[DataProvider('assignmentTexts')]
    public function test_it_classifies_assignment_context(string $text, DutyEventType $expected): void
    {
        $this->assertSame($expected, (new DutyTypeResolver)->resolve($text));
    }

    public static function assignmentTexts(): array
    {
        return [
            ['R1 LAX', DutyEventType::ReserveR1],
            ['5 6 7 ® ° 10 #311 © R2 Stn Vv"', DutyEventType::ReserveR2],
            ['r4 lax', DutyEventType::ReserveR4],
            ['SIM LAX', DutyEventType::Training],
            ['RECURRING', DutyEventType::Training],
            ['GROUND', DutyEventType::Training],
            ['CLASS', DutyEventType::Training],
            ['trng', DutyEventType::Training],
            ['Training', DutyEventType::Training],
            ['DH UA5445', DutyEventType::Deadhead],
            ['deadhead', DutyEventType::Deadhead],
            ['Flight Duty', DutyEventType::FlightDuty],
            ['Duty LT Jun 15 14:00', DutyEventType::FlightDuty],
            ['Flight Info', DutyEventType::FlightDuty],
            ['', DutyEventType::GeneralDuty],
            ['LAX', DutyEventType::GeneralDuty],
            ['R20 XR2 R4X CLASSIC SIM123 DHCP', DutyEventType::GeneralDuty],
        ];
    }

    public function test_legacy_event_types_are_normalized_under_duty(): void
    {
        $this->assertSame(ScheduleEventType::Duty, ScheduleEventType::fromValue('deadhead'));
        $this->assertSame(ScheduleEventType::Duty, ScheduleEventType::fromValue('training'));
        $this->assertSame(ScheduleEventType::Duty, ScheduleEventType::fromEvent(['type' => 'flight', 'metadata' => ['duty_type' => 'deadhead']]));
        $this->assertSame(DutyEventType::Deadhead, DutyEventType::fromEvent(['type' => 'deadhead']));
        $this->assertSame(DutyEventType::Training, DutyEventType::fromEvent(['type' => 'training']));
        $this->assertSame(DutyEventType::ReserveR2, DutyEventType::fromEvent(['type' => 'duty', 'metadata' => ['activity_code' => 'r2']]));
        $this->assertSame(['flight', 'duty'], ScheduleEventType::filterValues());
    }

    public function test_only_route_bearing_deadheads_use_flight_presentation(): void
    {
        $this->assertFalse(ScheduleEventType::isFlightEvent(['type' => 'deadhead']));
        $this->assertTrue(ScheduleEventType::isFlightEvent(['type' => 'duty', 'metadata' => [
            'duty_type' => 'deadhead', 'origin' => 'LAX', 'destination' => 'AUS',
        ]]));
        $this->assertFalse(ScheduleEventType::isFlightEvent(['type' => 'duty', 'metadata' => [
            'duty_type' => 'R2', 'origin' => 'LAX', 'destination' => 'LAX',
        ]]));
    }
}
