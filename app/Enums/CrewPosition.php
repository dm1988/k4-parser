<?php

namespace App\Enums;

enum CrewPosition: string
{
    case Captain = 'CA';
    case CaptainLong = 'CAPT';
    case FirstOfficer = 'FO';
    case Deadhead = 'DH';
    case FlightEngineer = 'FE';
    case FlightMechanicEngineer = 'FME';
    case AircraftCommander = 'AC';
    case Operations = 'OP';
    case AugmentedFirstOfficer = 'AFO';
    case AugmentedCrew = 'ACA';
    case Loadmaster = 'LM';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $position): string => $position->value,
            self::cases(),
        );
    }

    public static function regexPattern(): string
    {
        return implode('|', array_map(
            static fn (string $position): string => preg_quote($position, '/'),
            self::values(),
        ));
    }
}
