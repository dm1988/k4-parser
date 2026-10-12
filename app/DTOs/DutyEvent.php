<?php

namespace App\DTOs;

use App\Enums\DutyEventType;
use App\Enums\ScheduleEventType;

final readonly class DutyEvent extends ExtractedEventDTO
{
    public function __construct(
        string $title,
        string $type,
        string $typeLabel,
        string $typeDescription,
        string $typeIcon,
        string $scheduleLabel,
        string $durationLabel,
        bool $isDeadhead,
        string $badgeColor,
        string $downloadUrl,
        ?string $downloadId = null,
        public ?string $station = null,
        public ?string $activityCode = null,
        public ?string $layoverDuration = null,
        public ?int $crewCount = null,
        public ?int $operatingCrewCount = null,
        public ?int $deadheadingCrewCount = null,
        ?string $start = null,
        ?string $end = null,
        ?string $timezone = null,
        public array $rawLines = [],
        array $metadata = [],
        public DutyEventType $dutyType = DutyEventType::GeneralDuty,
    ) {
        parent::__construct(
            title: $title,
            type: $type,
            typeLabel: $typeLabel,
            typeDescription: $typeDescription,
            typeIcon: $typeIcon,
            scheduleLabel: $scheduleLabel,
            durationLabel: $durationLabel,
            isDeadhead: $isDeadhead,
            badgeColor: $badgeColor,
            downloadUrl: $downloadUrl,
            downloadId: $downloadId,
            start: $start,
            end: $end,
            timezone: $timezone,
            metadata: $metadata,
        );
    }

    public static function fromArray(array $data): self
    {
        $metadata = self::metadataFrom($data);
        $dutyType = DutyEventType::fromEvent($data);
        $metadata['duty_type'] = $dutyType->value;

        return new self(
            title: self::stringOrDefault($data, 'title'),
            type: ScheduleEventType::Duty->value,
            typeLabel: $dutyType->label(),
            typeDescription: $dutyType->description(),
            typeIcon: $dutyType->icon(),
            scheduleLabel: self::stringOrDefault($data, 'scheduleLabel', 'schedule_label'),
            durationLabel: self::stringOrDefault($data, 'durationLabel', 'duration_label'),
            isDeadhead: $dutyType === DutyEventType::Deadhead,
            badgeColor: $dutyType->badgeColor(),
            downloadUrl: self::stringOrDefault($data, 'downloadUrl', 'download_url'),
            downloadId: self::nullableString($data, 'downloadId', 'download_id'),
            station: self::nullableString($data, 'station') ?? self::nullableString($metadata, 'station'),
            activityCode: self::nullableString($data, 'activityCode', 'activity_code') ?? self::nullableString($metadata, 'activity_code'),
            layoverDuration: self::nullableString($data, 'layoverDuration', 'layover_duration') ?? self::nullableString($metadata, 'layover_duration'),
            crewCount: self::nullableInt($data, 'crewCount', 'crew_count') ?? self::nullableInt($metadata, 'crew_count'),
            operatingCrewCount: self::nullableInt($data, 'operatingCrewCount', 'operating_crew_count') ?? self::nullableInt($metadata, 'operating_crew_count'),
            deadheadingCrewCount: self::nullableInt($data, 'deadheadingCrewCount', 'deadheading_crew_count') ?? self::nullableInt($metadata, 'deadheading_crew_count'),
            start: self::nullableString($data, 'start'),
            end: self::nullableString($data, 'end'),
            timezone: self::nullableString($data, 'timezone'),
            rawLines: self::stringList($data['rawLines'] ?? $data['raw_lines'] ?? $metadata['raw_lines'] ?? []),
            metadata: $metadata,
            dutyType: $dutyType,
        );
    }

    public function toArray(): array
    {
        return [
            ...$this->baseArray(),
            'station' => $this->station,
            'dutyType' => $this->dutyType->value,
            'duty_type' => $this->dutyType->value,
            'activityCode' => $this->activityCode,
            'layoverDuration' => $this->layoverDuration,
            'crewCount' => $this->crewCount,
            'operatingCrewCount' => $this->operatingCrewCount,
            'deadheadingCrewCount' => $this->deadheadingCrewCount,
            'rawLines' => $this->rawLines,
            'activity_code' => $this->activityCode,
            'layover_duration' => $this->layoverDuration,
            'crew_count' => $this->crewCount,
            'operating_crew_count' => $this->operatingCrewCount,
            'deadheading_crew_count' => $this->deadheadingCrewCount,
            'raw_lines' => $this->rawLines,
        ];
    }

    public function withDownloadId(string $downloadId): static
    {
        return new self(
            title: $this->title,
            type: $this->type,
            typeLabel: $this->typeLabel,
            typeDescription: $this->typeDescription,
            typeIcon: $this->typeIcon,
            scheduleLabel: $this->scheduleLabel,
            durationLabel: $this->durationLabel,
            isDeadhead: $this->isDeadhead,
            badgeColor: $this->badgeColor,
            downloadUrl: $this->downloadUrl,
            downloadId: $downloadId,
            station: $this->station,
            activityCode: $this->activityCode,
            layoverDuration: $this->layoverDuration,
            crewCount: $this->crewCount,
            operatingCrewCount: $this->operatingCrewCount,
            deadheadingCrewCount: $this->deadheadingCrewCount,
            start: $this->start,
            end: $this->end,
            timezone: $this->timezone,
            rawLines: $this->rawLines,
            metadata: $this->metadata,
            dutyType: $this->dutyType,
        );
    }

    public function withDownloadUrl(string $downloadUrl): self
    {
        return new self(
            title: $this->title,
            type: $this->type,
            typeLabel: $this->typeLabel,
            typeDescription: $this->typeDescription,
            typeIcon: $this->typeIcon,
            scheduleLabel: $this->scheduleLabel,
            durationLabel: $this->durationLabel,
            isDeadhead: $this->isDeadhead,
            badgeColor: $this->badgeColor,
            downloadUrl: $downloadUrl,
            downloadId: $this->downloadId,
            station: $this->station,
            activityCode: $this->activityCode,
            layoverDuration: $this->layoverDuration,
            crewCount: $this->crewCount,
            operatingCrewCount: $this->operatingCrewCount,
            deadheadingCrewCount: $this->deadheadingCrewCount,
            start: $this->start,
            end: $this->end,
            timezone: $this->timezone,
            rawLines: $this->rawLines,
            metadata: $this->metadata,
            dutyType: $this->dutyType,
        );
    }
}
