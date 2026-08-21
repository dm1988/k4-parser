<?php

namespace App\Enums;

enum FlightPlanTask: string
{
    case Overview = 'overview';
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
}
