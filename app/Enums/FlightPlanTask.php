<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum FlightPlanTask: string
{
    case Overview = 'overview';
    case ReviewMelCdl = 'review_mel_cdl';
    case JeppPdPro = 'jepp_pd_pro';
    case MaintenanceLog = 'maintenance_log';
    case Envelope = 'envelope';
    case FlightInit = 'flight_init';
    case Fms = 'fms';
    case SlotTimes = 'slot_times';
    case FuelScore = 'fuel_score';
    case Etops = 'etops';
    case Weather = 'weather';
    case WeightAndBalance = 'weight_and_balance';

    public function label(): string
    {
        return match ($this) {
            self::Overview => 'Overview',
            self::ReviewMelCdl => 'Review MEL / CDL',
            self::JeppPdPro => 'Jepp PD-Pro',
            self::MaintenanceLog => 'Maintenance Log',
            self::Envelope => 'Envelope',
            self::FlightInit => 'Flight Init',
            self::Fms => 'FMS',
            self::SlotTimes => 'Slot Times',
            self::FuelScore => 'Fuel Score',
            self::Etops => 'ETOPS',
            self::Weather => 'Weather',
            self::WeightAndBalance => 'Weight & Balance',
        };
    }

    public function actionLabel(): string
    {
        return match ($this) {
            self::Overview => 'Review Overview',
            self::ReviewMelCdl => 'Review MEL / CDL',
            self::JeppPdPro => 'Check Jepp PD-Pro',
            self::MaintenanceLog => 'Check Maintenance Log',
            self::Envelope => 'Inspect Envelope',
            self::FlightInit => 'ACARS Initialize Flight',
            self::Fms => 'Program FMS',
            self::SlotTimes => 'Review Slot Times',
            self::FuelScore => 'Score Fuel',
            self::Etops => 'Review ETOPS',
            self::Weather => 'Check Weather Briefing',
            self::WeightAndBalance => 'Verify Weight & Balance',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Overview => 'home',
            self::ReviewMelCdl => 'wrench-screwdriver',
            self::JeppPdPro => 'paper-airplane',
            self::MaintenanceLog => 'clipboard-document-list',
            self::Envelope => 'document-chart-bar',
            self::FlightInit => 'bolt',
            self::Fms => 'calculator',
            self::SlotTimes => 'clock',
            self::FuelScore => 'chart-bar-square',
            self::Etops => 'globe-alt',
            self::Weather => 'cloud',
            self::WeightAndBalance => 'scale',
        };
    }

    public function componentName(): string
    {
        return 'flight-release.'.Str::kebab(Str::replace('_', ' ', $this->value));
    }

    public function requiresAirports(): bool
    {
        return in_array($this, [self::JeppPdPro, self::Fms], true);
    }

    public function hasCustomView(): bool
    {
        return true;
    }
}
