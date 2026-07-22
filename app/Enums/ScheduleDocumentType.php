<?php

namespace App\Enums;

enum ScheduleDocumentType: string
{
    case TripInformation = 'trip_information';
    case PublishedRoster = 'published_roster';
    case Image = 'image';
    // case Unknown = 'unknown';

    public function parserType(): string
    {
        return match ($this) {
            self::TripInformation => 'trip_pdf',
            self::PublishedRoster => 'roster_pdf',
            self::Image => 'screenshot',
            // self::Unknown => 'Unknown',
        };
    }
}
