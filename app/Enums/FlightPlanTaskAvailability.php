<?php

namespace App\Enums;

enum FlightPlanTaskAvailability: string
{
    case Available = 'available';
    case NotPresent = 'not_present';
    case NotSupported = 'not_supported';
}
