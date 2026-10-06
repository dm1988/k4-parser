<?php

namespace Tests\Unit\Enums;

use App\Enums\WaypointKind;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class WaypointKindTest extends TestCase
{
    #[DataProvider('sourceKinds')]
    public function test_it_resolves_source_identifiers_and_kind_values(string $identifier, mixed $kind, WaypointKind $expected): void
    {
        $this->assertSame($expected, WaypointKind::fromIdentifier($identifier, $kind));
    }

    public function test_the_supplied_kind_is_optional(): void
    {
        $this->assertSame(WaypointKind::Toc, WaypointKind::fromIdentifier('TOC'));
        $this->assertSame(WaypointKind::Tod, WaypointKind::fromIdentifier('TOD'));
        $this->assertSame(WaypointKind::Fix, WaypointKind::fromIdentifier('FIX01'));
    }

    /** @return iterable<string, array{string, mixed, WaypointKind}> */
    public static function sourceKinds(): iterable
    {
        yield 'TOC without metadata' => ['TOC', null, WaypointKind::Toc];
        yield 'TOC overrides conflicting metadata' => ['TOC', 'fir', WaypointKind::Toc];
        yield 'TOD without metadata' => ['TOD', null, WaypointKind::Tod];
        yield 'TOD overrides malformed metadata' => ['TOD', [], WaypointKind::Tod];
        yield 'explicit fix' => ['FIX01', 'fix', WaypointKind::Fix];
        yield 'explicit FIR' => ['-EDWW', 'fir', WaypointKind::Fir];
        yield 'explicit TOC' => ['MARKER', 'toc', WaypointKind::Toc];
        yield 'explicit TOD' => ['MARKER', 'tod', WaypointKind::Tod];
        yield 'missing kind' => ['FIX01', null, WaypointKind::Fix];
        yield 'unknown kind' => ['FIX01', 'unsupported', WaypointKind::Fix];
        yield 'numeric kind' => ['FIX01', 1, WaypointKind::Fix];
        yield 'array kind' => ['FIX01', [], WaypointKind::Fix];
        yield 'boolean kind' => ['FIX01', true, WaypointKind::Fix];
    }
}
