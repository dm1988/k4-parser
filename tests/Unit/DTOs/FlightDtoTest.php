<?php

namespace Tests\Unit\DTOs;

use App\DTOs\Flight;
use App\Mappers\FlightMapper;
use Tests\TestCase;

class FlightDtoTest extends TestCase
{
    public function test_it_builds_a_flight_dto_from_a_calendar_event(): void
    {
        $dto = app(FlightMapper::class)->fromCalendarEvent([
            'title' => 'CKS 240 ICN-HKG',
            'type' => 'flight',
            'start' => '2026-06-15T23:45:00+00:00',
            'end' => '2026-06-16T03:45:00+00:00',
            'timezone' => 'UTC',
            'metadata' => [
                'flight_number' => 'CKS 240',
                'origin' => 'ICN',
                'destination' => 'HKG',
                'position' => 'FO',
                'aircraft' => '77X',
                'tail_number' => 'N772CK',
                'flightaware_url' => 'https://www.flightaware.com/live/flight/N772CK',
                'block_time' => '4:00h',
                'trip_id' => '13131',
                'crew_count' => 4,
                'operating_crew_count' => 3,
                'deadheading_crew_count' => 1,
                'duty_station' => 'ICN',
                'deadhead' => false,
                'raw_lines' => ['ICN-HKG | AFO 77X 4:00h'],
                'duty_raw_lines' => ['Crew list'],
            ],
        ]);

        $this->assertNotNull($dto);
        $this->assertSame('CKS 240', $dto->flightNumber);
        $this->assertSame('ICN', $dto->origin);
        $this->assertSame('HKG', $dto->destination);
        $this->assertSame('FO', $dto->position);
        $this->assertSame('77X', $dto->aircraft);
        $this->assertSame('N772CK', $dto->tailNumber);
        $this->assertSame('https://www.flightaware.com/live/flight/N772CK', $dto->downloadUrl);
        $this->assertSame('Jun 15, 11:45 PM -> Jun 16, 3:45 AM', $dto->scheduleLabel);
        $this->assertSame('4:00h', $dto->durationLabel);
        $this->assertSame(4, $dto->crewCount);
        $this->assertSame(['Crew list'], $dto->dutyRawLines);
    }

    public function test_it_returns_null_for_non_flight_events(): void
    {
        $dto = app(FlightMapper::class)->fromCalendarEvent([
            'title' => 'Layover HKG',
            'type' => 'layover',
            'metadata' => [
                'station' => 'HKG',
            ],
        ]);

        $this->assertNull($dto);
    }

    public function test_it_normalizes_snake_case_payloads(): void
    {
        $dto = Flight::fromArray([
            'title' => 'CKS 206 CVG-NRT',
            'type' => 'flight',
            'type_label' => 'Flight',
            'schedule_label' => 'Jun 13 • 9:35 AM - 11:25 PM',
            'duration_label' => '13h 50m',
            'tail_number' => 'N123CK',
            'is_deadhead' => 1,
            'badge_color' => 'bg-blue-100 text-blue-900',
            'download_url' => 'https://example.test/export',
            'flight_number' => 'CKS 206',
            'block_time' => '13:50h',
            'trip_id' => '13131',
            'crew_count' => '4',
            'raw_lines' => ['line 1', '', 'line 2'],
            'metadata' => [
                'origin' => 'CVG',
                'destination' => 'NRT',
                'operating_crew_count' => 3,
            ],
        ]);

        $this->assertSame('Flight', $dto->typeLabel);
        $this->assertTrue($dto->isDeadhead);
        $this->assertSame('CKS 206', $dto->flightNumber);
        $this->assertSame('13:50h', $dto->blockTime);
        $this->assertSame('13131', $dto->tripId);
        $this->assertSame(4, $dto->crewCount);
        $this->assertSame(3, $dto->operatingCrewCount);
        $this->assertSame(['line 1', 'line 2'], $dto->rawLines);
        $this->assertSame('CVG', $dto->origin);
        $this->assertSame('NRT', $dto->destination);
    }

    public function test_it_can_round_trip_a_flight_dto_back_to_calendar_event(): void
    {
        $mapper = app(FlightMapper::class);
        $flight = Flight::fromArray([
            'title' => 'CKS 206 CVG-NRT',
            'type' => 'flight',
            'typeLabel' => 'Flight',
            'typeDescription' => 'Scheduled flying segment.',
            'typeIcon' => 'heroicon-o-paper-airplane',
            'scheduleLabel' => 'Jun 13 • 9:35 AM - 11:25 PM',
            'durationLabel' => '13h 50m',
            'tailNumber' => 'N123CK',
            'isDeadhead' => false,
            'badgeColor' => 'bg-blue-100 text-blue-900',
            'downloadUrl' => 'https://example.test/export',
            'downloadId' => '01JTESTEVENTKEYABC123',
            'flightNumber' => 'CKS 206',
            'aircraft' => '77X',
            'blockTime' => '13:50h',
            'tripId' => '13131',
            'start' => '2026-06-13T09:35:00+00:00',
            'end' => '2026-06-13T23:25:00+00:00',
            'origin' => 'CVG',
            'destination' => 'NRT',
            'metadata' => ['position' => 'FO'],
        ]);

        $event = $mapper->toCalendarEvent($flight);

        $this->assertSame('flight', $event['type']);
        $this->assertSame('01JTESTEVENTKEYABC123', $event['download_id']);
        $this->assertSame('CKS 206', $event['metadata']['flight_number']);
        $this->assertSame('CVG', $event['metadata']['origin']);
        $this->assertSame('NRT', $event['metadata']['destination']);
        $this->assertSame('FO', $event['metadata']['position']);
    }
}
