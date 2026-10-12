<?php

namespace Tests\Unit\DTOs;

use App\DTOs\DutyEvent;
use App\Enums\DutyEventType;
use App\Mappers\DutyEventMapper;
use PHPUnit\Framework\Attributes\DataProvider;
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
        $this->assertSame('R2', $dto->typeLabel);
        $this->assertSame(DutyEventType::ReserveR2, $dto->dutyType);
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

        $this->assertSame('R2', $dto->typeLabel);
        $this->assertSame('ANC', $dto->station);
        $this->assertSame('R2', $dto->activityCode);
        $this->assertSame(3, $dto->operatingCrewCount);
    }

    public function test_it_truncates_partial_minutes_when_building_duration_labels(): void
    {
        $dto = app(DutyEventMapper::class)->fromCalendarEvent([
            'title' => 'Duty CVG',
            'type' => 'duty',
            'start' => '2026-06-13T07:35:00+00:00',
            'end' => '2026-06-13T08:35:59+00:00',
        ]);

        $this->assertNotNull($dto);
        $this->assertSame('1h 0m', $dto->durationLabel);
    }

    #[DataProvider('dutyTypes')]
    public function test_duty_badges_survive_serialization_and_download_updates(DutyEventType $dutyType): void
    {
        $dto = app(DutyEventMapper::class)->fromCalendarEvent([
            'title' => $dutyType->label().' LAX',
            'type' => 'duty',
            'metadata' => ['duty_type' => $dutyType->value, 'station' => 'LAX'],
        ]);
        $this->assertNotNull($dto);

        $restored = DutyEvent::fromArray($dto->toArray())->withDownloadId('event-id')->withDownloadUrl('/export');

        $this->assertSame($dutyType, $restored->dutyType);
        $this->assertSame('duty', $restored->type);
        $this->assertSame($dutyType->label(), $restored->typeLabel);
        $this->assertSame($dutyType->badgeColor(), $restored->badgeColor);
        $this->assertSame($dutyType->value, $restored->metadata['duty_type']);
        $this->assertSame('event-id', $restored->downloadId);
        $this->assertSame('/export', $restored->downloadUrl);
    }

    public static function dutyTypes(): array
    {
        return array_map(static fn (DutyEventType $type): array => [$type], DutyEventType::cases());
    }

    public function test_it_normalizes_legacy_training_and_unknown_duty_payloads(): void
    {
        $training = DutyEvent::fromArray(['type' => 'training']);
        $this->assertSame('duty', $training->type);
        $this->assertSame(DutyEventType::Training, $training->dutyType);
        $this->assertSame('Training', $training->typeLabel);
        $this->assertSame(DutyEventType::GeneralDuty, DutyEvent::fromArray(['duty_type' => 'invalid'])->dutyType);
    }

    public function test_it_reads_legacy_meta_and_camel_case_duty_type_fields(): void
    {
        $this->assertSame(DutyEventType::ReserveR4, DutyEvent::fromArray(['meta' => ['activity_code' => 'R4']])->dutyType);
        $this->assertSame(DutyEventType::Training, DutyEvent::fromArray(['dutyType' => 'training'])->dutyType);
    }
}
