<?php

namespace App\Enums;

enum ReleaseStatus: string
{
    case Preliminary = 'preliminary';
    case NotMarkedPreliminary = 'not_marked_preliminary';
    case Unknown = 'unknown';
}
