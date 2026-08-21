<?php

namespace App\Enums;

enum TimeBasis: string
{
    case Utc = 'utc';
    case Local = 'local';
}
