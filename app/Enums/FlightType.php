<?php

namespace App\Enums;

enum FlightType: string
{
    case Domestic = 'domestic';
    case International = 'international';
    case Unknown = 'unknown';
}
