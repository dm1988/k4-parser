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
        $this->assertStringContainsString('bg-emerald-700', CrewPosition::PilotInCommand->badgeColor());
        $this->assertStringContainsString('bg-blue-600', CrewPosition::SecondInCommand->badgeColor());
        $this->assertStringContainsString('bg-amber-700', CrewPosition::InternationalReliefPilot->badgeColor());
        $this->assertStringContainsString('bg-purple-600', CrewPosition::AdditionalCrewMember->badgeColor());
        $this->assertStringContainsString('bg-[#1B365D]', CrewPosition::Loadmaster->badgeColor());

        foreach (CrewPosition::cases() as $position) {
            $this->assertStringContainsString('dark:', $position->badgeColor());
        }
    }

    public function test_every_role_and_fallback_palette_meets_normal_text_contrast(): void
    {
        $colors = [
            'white' => [255, 255, 255],
            'emerald-700' => [4, 120, 87],
            'emerald-500' => [16, 185, 129],
            'emerald-400' => [52, 211, 153],
            'blue-600' => [37, 99, 235],
            'blue-500' => [59, 130, 246],
            'blue-400' => [96, 165, 250],
            'amber-700' => [180, 83, 9],
            'amber-500' => [245, 158, 11],
            'amber-400' => [251, 191, 36],
            'purple-600' => [147, 51, 234],
            'purple-500' => [168, 85, 247],
            'purple-400' => [192, 132, 252],
            '[#1B365D]' => [27, 54, 93],
            'slate-700' => [51, 65, 85],
            'slate-100' => [241, 245, 249],
        ];

        $palettes = array_map(fn (CrewPosition $role): string => $role->badgeColor(), CrewPosition::cases());
        $palettes[] = CrewPosition::defaultBadgeColor();

        foreach ($palettes as $palette) {
            $this->assertSame(1, preg_match('/^bg-(\S+) text-(\S+) dark:bg-(\S+) dark:text-(\S+)$/', $palette, $matches));
            $this->assertGreaterThanOrEqual(4.5, $this->contrast($colors[$matches[1]], $colors[$matches[2]]), $palette.' light');
            [$background, $opacity] = array_pad(explode('/', $matches[3]), 2, '100');
            $composite = array_map(
                fn (int $channel, int $surface): float => $channel * (float) $opacity / 100 + $surface * (1 - (float) $opacity / 100),
                $colors[$background],
                [15, 23, 42],
            );
            $this->assertGreaterThanOrEqual(4.5, $this->contrast($composite, $colors[$matches[4]]), $palette.' dark');
        }
    }

    /** @param list<int|float> $first @param list<int|float> $second */
    private function contrast(array $first, array $second): float
    {
        $luminance = static function (array $rgb): float {
            $linear = array_map(static function (int|float $channel): float {
                $channel /= 255;

                return $channel <= 0.04045 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
            }, $rgb);

            return $linear[0] * 0.2126 + $linear[1] * 0.7152 + $linear[2] * 0.0722;
        };
        $left = $luminance($first);
        $right = $luminance($second);

        return (max($left, $right) + 0.05) / (min($left, $right) + 0.05);
    }
}
