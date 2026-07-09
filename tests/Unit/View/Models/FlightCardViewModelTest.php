<?php

namespace Tests\Unit\View\Models;

use App\DTOs\Flight;
use App\View\Models\Parser\FlightCardViewModel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FlightCardViewModelTest extends TestCase
{
    #[Test]
    public function it_builds_flight_card_fields_from_a_flight_dto(): void
    {
        $flight = Flight::fromArray([
            'title' => 'CKS 240 ICN-HKG',
            'type' => 'flight',
            'typeLabel' => 'Flight',
            'typeDescription' => 'Scheduled flying segment.',
            'scheduleLabel' => 'Jun 15, 11:45 PM -> Jun 16, 3:45 AM',
            'durationLabel' => '4:00h',
            'tailNumber' => 'N772CK',
            'isDeadhead' => false,
            'badgeColor' => 'bg-blue-100 text-blue-900',
            'downloadUrl' => 'https://example.test/export',
            'flightNumber' => 'CKS 240',
            'legLocalStart' => 'Jun 16 08:45',
            'legLocalEnd' => 'Jun 16 12:45',
            'dutyLocalStart' => 'Jun 16 07:15',
            'dutyLocalEnd' => 'Jun 16 13:30',
            'start' => '2026-06-15T23:45:00+00:00',
            'end' => '2026-06-16T03:45:00+00:00',
            'origin' => 'ICN',
            'destination' => 'HKG',
            'metadata' => [
                'origin_name' => 'Incheon International Airport',
                'origin_icao' => 'RKSI',
                'origin_city' => 'Seoul',
                'origin_country_code' => 'KR',
                'destination_name' => 'Hong Kong International Airport',
                'destination_icao' => 'VHHH',
                'destination_city' => 'Hong Kong',
                'destination_country_code' => 'HK',
            ],
        ]);

        $model = FlightCardViewModel::fromFlight($flight);

        $this->assertSame($flight, $model->flight);
        $this->assertSame('CKS 240', $model->heading());
        $this->assertSame('ICN', $model->originLabel());
        $this->assertSame('HKG', $model->destinationLabel());
        $this->assertSame('11:45 PM', $model->originTimeLabel());
        $this->assertSame('3:45 AM', $model->destinationTimeLabel());
        $this->assertSame('23:45 Z', $model->originCardTimeLabel());
        $this->assertSame('03:45 Z', $model->destinationCardTimeLabel());
        $this->assertTrue($model->hasLegLocalTimes());
        $this->assertSame('Jun 16 08:45', $model->legLocalStartLabel());
        $this->assertSame('Jun 16 12:45', $model->legLocalEndLabel());
        $this->assertSame('Jun 16 08:45 - Jun 16 12:45', $model->legLocalTimesLabel());
        $this->assertTrue($model->hasDutyLocalTimes());
        $this->assertSame('Jun 16 07:15', $model->dutyLocalStartLabel());
        $this->assertSame('Jun 16 13:30', $model->dutyLocalEndLabel());
        $this->assertSame('Jun 16 07:15 - Jun 16 13:30', $model->dutyLocalTimesLabel());
        $this->assertTrue($model->hasAirportDetails());
        $this->assertSame('RKSI', $model->originIcao());
        $this->assertSame('VHHH', $model->destinationIcao());
    }

    #[Test]
    public function it_falls_back_to_type_label_for_non_numbered_events(): void
    {
        $flight = Flight::fromArray([
            'title' => 'Duty CVG',
            'type' => 'duty',
            'typeLabel' => 'Duty',
            'typeDescription' => 'On-duty time without a flight or layover segment.',
            'scheduleLabel' => 'Jun 13 • 7:35 AM - 9:35 AM',
            'durationLabel' => '2:00',
            'isDeadhead' => false,
            'badgeColor' => 'bg-green-100 text-green-900',
            'downloadUrl' => 'https://example.test/export',
        ]);

        $model = FlightCardViewModel::fromFlight($flight);

        $this->assertSame('Duty', $model->heading());
        $this->assertFalse($model->hasAirportDetails());
        $this->assertFalse($model->hasLegLocalTimes());
        $this->assertFalse($model->hasDutyLocalTimes());
        $this->assertSame('UNK', $model->originLabel());
        $this->assertSame('UNK', $model->destinationLabel());
    }

    #[Test]
    public function it_uses_flight_numbers_for_deadhead_flights(): void
    {
        $flight = Flight::fromArray([
            'title' => 'CKS 240 ICN-HKG',
            'type' => 'deadhead',
            'typeLabel' => 'Deadhead',
            'typeDescription' => 'Time spent traveling as a passenger for work purposes.',
            'scheduleLabel' => 'Jun 15, 11:45 PM -> Jun 16, 3:45 AM',
            'durationLabel' => '4:00h',
            'isDeadhead' => true,
            'badgeColor' => 'bg-yellow-100 text-yellow-900',
            'downloadUrl' => 'https://example.test/export',
            'flightNumber' => 'CKS 240',
        ]);

        $model = FlightCardViewModel::fromFlight($flight);

        $this->assertSame('CKS 240', $model->heading());
    }
}
