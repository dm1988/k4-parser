<?php

namespace Tests\Unit\Enums;

use App\Enums\CrewPosition;
use PHPUnit\Framework\TestCase;

class CrewPositionTest extends TestCase
{
    public function test_it_exposes_compact_badge_labels(): void
    {
        $this->assertSame('PIC', CrewPosition::PilotInCommand->badgeLabel());
        $this->assertSame('SIC', CrewPosition::SecondInCommand->badgeLabel());
        $this->assertSame('CAPT', CrewPosition::AdditionalCaptain->badgeLabel());
    }

    public function test_it_owns_role_badge_colors(): void
    {
        $this->assertStringContainsString('bg-emerald-600', CrewPosition::PilotInCommand->badgeColor());
        $this->assertStringContainsString('bg-blue-600', CrewPosition::SecondInCommand->badgeColor());
        $this->assertStringContainsString('bg-amber-600', CrewPosition::InternationalReliefPilot->badgeColor());
        $this->assertStringContainsString('bg-purple-600', CrewPosition::AdditionalCrewMember->badgeColor());
        $this->assertStringContainsString('bg-[#1B365D]', CrewPosition::Loadmaster->badgeColor());

        foreach (CrewPosition::cases() as $position) {
            $this->assertStringContainsString('dark:', $position->badgeColor());
        }
    }
}
