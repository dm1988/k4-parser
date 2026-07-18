<?php

namespace App\Enums;

enum ScheduleDocumentType: string
{
    case TripInformation = 'trip_information';
    case PublishedRoster = 'published_roster';

    public function parserType(): string
    {
        return match ($this) {
            self::TripInformation => 'trip_pdf',
            self::PublishedRoster => 'roster_pdf',
        };
    }
}
