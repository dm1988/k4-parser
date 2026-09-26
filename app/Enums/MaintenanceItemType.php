<?php

namespace App\Enums;

enum MaintenanceItemType: string
{
    case Mel = 'MEL';
    case Cdl = 'CDL';
    case Nef = 'NEF';
    case Dmi = 'DMI';

    /** @return list<self> */
    public static function counterPriority(): array
    {
        return [self::Mel, self::Cdl, self::Nef, self::Dmi];
    }

    public function title(): string
    {
        return match ($this) {
            self::Mel => 'Minimum Equipment List',
            self::Cdl => 'Configuration Deviation List',
            self::Nef => 'Non-Essential Equipment & Furnishings',
            self::Dmi => 'Deferred Maintenance Item',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Mel => 'MEL items involve required aircraft systems or instruments. They carry strict operational constraints, specific flight conditions (e.g., "Day VFR only"), and hard calendar deadlines.',
            self::Cdl => 'CDL items involve missing external parts (like a missing aerodynamic fairing, static wick, or flap seal). They directly impact performance, fuel burn, or weight limitations.',
            self::Nef => 'NEF items are strictly cosmetic or passenger-convenience features (like a broken passenger seat recline or a chipped galley trim). They have zero impact on safety, airworthiness, or performance.',
            self::Dmi => 'DMI is a broad category used for tracking parts on order, planned maintenance tasks, or open discrepancies that do not ground the aircraft but need attention.',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Mel => 'bg-red-100 text-red-900 dark:bg-red-400/15 dark:text-red-200',
            self::Cdl => 'bg-orange-100 text-orange-900 dark:bg-orange-400/15 dark:text-orange-200',
            self::Nef => 'bg-gray-100 text-gray-900 dark:bg-gray-700 dark:text-gray-100',
            self::Dmi => 'bg-yellow-100 text-yellow-900 dark:bg-yellow-400/15 dark:text-yellow-200',
        };
    }

    public function overviewCardClasses(): string
    {
        return match ($this) {
            self::Mel => 'border-red-500/30 border-l-4 border-l-red-500 bg-red-500/5 backdrop-blur dark:border-red-400/30 dark:border-l-red-400 dark:bg-red-400/10',
            self::Cdl => 'border-orange-500/30 border-l-4 border-l-orange-500 bg-orange-500/5 backdrop-blur dark:border-orange-400/30 dark:border-l-orange-400 dark:bg-orange-400/10',
            self::Nef => 'border-gray-500/30 border-l-4 border-l-gray-500 bg-gray-500/5 backdrop-blur dark:border-gray-400/30 dark:border-l-gray-400 dark:bg-gray-400/10',
            self::Dmi => 'border-yellow-500/30 border-l-4 border-l-yellow-500 bg-yellow-500/5 backdrop-blur dark:border-yellow-400/30 dark:border-l-yellow-400 dark:bg-yellow-400/10',
        };
    }
}
