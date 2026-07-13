<?php

namespace App\Enums;

enum MetadataKey: string
{
    case FlightNumber = 'flight_number';
    case Origin = 'origin';
    case Destination = 'destination';
    case Position = 'position';
    case Aircraft = 'aircraft';
    case TailNumber = 'tail_number';
    case FlightawareUrl = 'flightaware_url';
    case BlockTime = 'block_time';
    case TripId = 'trip_id';
    case CrewCount = 'crew_count';
    case OperatingCrewCount = 'operating_crew_count';
    case DeadheadingCrewCount = 'deadheading_crew_count';
    case DutyStation = 'duty_station';
    case LegLocalStart = 'leg_local_start';
    case LegLocalEnd = 'leg_local_end';
    case DutyLocalStart = 'duty_local_start';
    case DutyLocalEnd = 'duty_local_end';
    case RawLines = 'raw_lines';
    case DutyRawLines = 'duty_raw_lines';
    case Deadhead = 'deadhead';
    case DownloadUrl = 'download_url';
    case DownloadId = 'download_id';
    case UtcStart = 'utc_start';
    case UtcEnd = 'utc_end';
    case LocalStart = 'local_start';
    case LocalEnd = 'local_end';
    case Station = 'station';
    case ActivityCode = 'activity_code';
    case LayoverDuration = 'layover_duration';
    case FlightUtcStart = 'flight_utc_start';
    case FlightUtcEnd = 'flight_utc_end';
    case DutyUtcStart = 'duty_utc_start';
    case DutyUtcEnd = 'duty_utc_end';
    case Crew = 'crew';

    public static function values(): array
    {
        return array_map(static fn (self $k) => $k->value, self::cases());
    }

    public function metadataLabel(): ?string
    {
        return match ($this) {
            self::CrewCount => 'Crew count',
            self::OperatingCrewCount => 'Operating crew count',
            self::DeadheadingCrewCount => 'Deadheading crew count',
            self::FlightNumber => 'Flight number',
            self::Origin => 'Origin',
            self::Destination => 'Destination',
            self::TailNumber => 'Tail number',
            self::BlockTime => 'Block time',
            self::TripId => 'Trip ID',
            self::Station => 'Station',
            self::ActivityCode => 'Activity code',
            self::LayoverDuration => 'Layover duration',
            self::FlightUtcStart => 'Flight UTC start',
            self::FlightUtcEnd => 'Flight UTC end',
            self::DutyUtcStart => 'Duty UTC start',
            self::DutyUtcEnd => 'Duty UTC end',
            self::Crew => 'Crew',
            default => null,
        };
    }

    public function metadataPrefix(): string
    {
        return '• ';
    }

    public function metadataSuffix(): string
    {
        return ': ';
    }
}
