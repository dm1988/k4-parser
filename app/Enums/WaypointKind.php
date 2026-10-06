<?php

namespace App\Enums;

enum WaypointKind: string
{
    case Fix = 'fix';
    case Fir = 'fir';
    case Toc = 'toc';
    case Tod = 'tod';

    public static function fromIdentifier(string $identifier, mixed $kind = null): self
    {
        return match ($identifier) {
            'TOC' => self::Toc,
            'TOD' => self::Tod,
            default => self::tryFrom(is_string($kind) ? $kind : '') ?? self::Fix,
        };
    }
}
