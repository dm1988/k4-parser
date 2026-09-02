<?php

namespace App\Enums;

enum CrewPosition: string
{
    case Captain = 'CA';
    case CaptainPilot = 'CP';
    case CaptainLong = 'CAPT';
    case PilotInCommand = 'PIC';
    case SecondInCommand = 'SIC/FO';
    case AdditionalCaptain = 'ADDNTL CAPT';
    case InternationalReliefPilot = 'IRP';
    case FirstOfficer = 'FO';
    case Deadhead = 'DH';
    case FlightEngineer = 'FE';
    case FlightMechanicEngineer = 'FME';
    case MaintenancePersonnel = 'MX';
    case AircraftCommander = 'AC';
    case Operations = 'OP';
    case Observer = 'OB';
    case AugmentedFirstOfficer = 'AFO';
    case AugmentedCrew = 'ACA';
    case AdditionalCrewMember = 'ACM';
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
        $positions = self::values();
        usort(
            $positions,
            static fn (string $left, string $right): int => strlen($right) <=> strlen($left),
        );

        return implode('|', array_map(
            static fn (string $position): string => preg_quote($position, '/'),
            $positions,
        ));
    }
}
