<?php

namespace Tests\Unit\DTOs;

use App\DTOs\DutyEvent;
use App\Mappers\DutyEventMapper;
use Tests\TestCase;

class DutyEventDtoTest extends TestCase
{
    public function test_it_builds_a_duty_dto_from_a_calendar_event(): void
    {
        $dto = app(DutyEventMapper::class)->fromCalendarEvent([
            'title' => 'Duty CVG',
            'type' => 'duty',
            'start' => '2026-06-13T07:35:00+00:00',
            'end' => '2026-06-13T09:35:00+00:00',
            'timezone' => 'UTC',
            'download_id' => '01JTESTEVENTKEYABC123',
            'metadata' => [
                'station' => 'CVG',
                'activity_code' => 'R2',
                'layover_duration' => '2:00',
                'crew_count' => 4,
                'operating_crew_count' => 3,
                'deadheading_crew_count' => 1,
                'raw_lines' => ['Crew list'],
            ],
        ]);

        $this->assertNotNull($dto);
        $this->assertSame('Duty', $dto->typeLabel);
        $this->assertSame('CVG', $dto->station);
        $this->assertSame('R2', $dto->activityCode);
        $this->assertSame('2:00', $dto->layoverDuration);
        $this->assertSame('Jun 13 • 0735 Z - 0935 Z', $dto->scheduleLabel);
        $this->assertSame('2h 0m', $dto->durationLabel);
        $this->assertSame(['Crew list'], $dto->rawLines);
    }

    public function test_it_normalizes_snake_case_payloads(): void
    {
        $dto = DutyEvent::fromArray([
            'title' => 'Duty ANC',
            'type' => 'duty',
            'type_label' => 'Duty',
            'type_description' => 'On-duty time without a flight or layover segment.',
            'type_icon' => 'heroicon-o-briefcase',
            'schedule_label' => 'Jun 13 • 7:35 AM - 9:35 AM',
            'duration_label' => '2h 0m',
            'badge_color' => 'bg-green-100 text-green-900',
            'download_url' => 'https://example.test/export',
            'activity_code' => 'R2',
            'metadata' => [
                'station' => 'ANC',
                'operating_crew_count' => 3,
            ],
        ]);

        $this->assertSame('Duty', $dto->typeLabel);
        $this->assertSame('ANC', $dto->station);
        $this->assertSame('R2', $dto->activityCode);
        $this->assertSame(3, $dto->operatingCrewCount);
    }
}
