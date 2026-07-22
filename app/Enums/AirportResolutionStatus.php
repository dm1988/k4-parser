<?php

namespace App\Enums;

enum AirportResolutionStatus: string
{
    case Found = 'found';
    case Missing = 'missing';
    case Unavailable = 'unavailable';
}
