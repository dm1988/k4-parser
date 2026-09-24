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

    public function badgeLabel(): string
    {
        return match ($this) {
            self::SecondInCommand => 'SIC',
            self::AdditionalCaptain => 'CAPT',
            default => $this->value,
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Captain,
            self::CaptainPilot,
            self::CaptainLong,
            self::PilotInCommand,
            self::AdditionalCaptain => 'bg-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400',
            self::SecondInCommand,
            self::FirstOfficer,
            self::AugmentedFirstOfficer => 'bg-blue-600 dark:bg-blue-500/20 dark:text-blue-400',
            self::InternationalReliefPilot => 'bg-amber-600 dark:bg-amber-500/20 dark:text-amber-400',
            self::AugmentedCrew,
            self::AdditionalCrewMember => 'bg-purple-600 dark:bg-purple-500/20 dark:text-purple-400',
            default => self::defaultBadgeColor(),
        };
    }

    public static function defaultBadgeColor(): string
    {
        return 'bg-[#1B365D] dark:bg-slate-700 dark:text-slate-100';
    }

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
