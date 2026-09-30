<?php

namespace App\Enums;

enum WaypointKind: string
{
    case Fix = 'fix';
    case Fir = 'fir';
    case Toc = 'toc';
    case Tod = 'tod';
}
