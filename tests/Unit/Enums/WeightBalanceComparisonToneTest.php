<?php

namespace Tests\Unit\Enums;

use App\Enums\WeightBalanceComparisonTone;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class WeightBalanceComparisonToneTest extends TestCase
{
    private const array COLORS = [
        'white' => 'ffffff',
        'slate-100' => 'f1f5f9',
        'slate-400' => '94a3b8',
        'slate-600' => '475569',
        'slate-800' => '1e293b',
        'slate-900' => '0f172a',
        'brand' => '1b365d',
        'emerald-100' => 'd1fae5',
        'emerald-300' => '6ee7b7',
        'emerald-400' => '34d399',
        'emerald-700' => '047857',
        'sky-100' => 'e0f2fe',
        'sky-300' => '7dd3fc',
        'sky-400' => '38bdf8',
        'sky-700' => '0369a1',
        'amber-100' => 'fef3c7',
        'amber-300' => 'fcd34d',
        'amber-400' => 'fbbf24',
        'amber-700' => 'b45309',
        'amber-800' => '92400e',
        'rose-100' => 'ffe4e6',
        'rose-300' => 'fda4af',
        'rose-400' => 'fb7185',
        'rose-700' => 'be123c',
    ];

    #[Test]
    public function it_exposes_consistent_text_badge_and_card_hues_in_both_themes(): void
    {
        foreach (WeightBalanceComparisonTone::cases() as $tone) {
            $hue = match ($tone) {
                WeightBalanceComparisonTone::Safe => 'emerald',
                WeightBalanceComparisonTone::Heavy => 'sky',
                WeightBalanceComparisonTone::Caution => 'amber',
                WeightBalanceComparisonTone::Exceeded => 'rose',
            };

            $this->assertSame('text-'.$hue.'-700 dark:text-'.$hue.'-400', $tone->textClasses());
            $this->assertSame('cc-weight-progress-'.$tone->value, $tone->progressClass());
            $this->assertStringContainsString('bg-'.$hue.'-100', $tone->badgeClasses());
            $this->assertStringContainsString('dark:bg-'.$hue.'-400/15', $tone->badgeClasses());
            $this->assertStringContainsString('dark:text-'.$hue.'-300', $tone->badgeClasses());
            $this->assertStringContainsString('border-slate-200 border-l-4', $tone->overviewCardClasses());
            $this->assertStringContainsString('dark:border-slate-700 dark:border-l-'.$hue.'-400', $tone->overviewCardClasses());
            $this->assertStringContainsString('bg-'.$hue.'-500/5', $tone->overviewCardClasses());
            $this->assertStringNotContainsString('text-white', $tone->badgeClasses());
        }
    }

    #[Test]
    public function text_and_badges_meet_contrast_on_cards_and_selected_navigation(): void
    {
        foreach (WeightBalanceComparisonTone::cases() as $tone) {
            foreach ([false, true] as $dark) {
                $surface = $this->rgb(self::COLORS[$dark ? 'slate-900' : 'white']);
                $text = $this->utilityColor($tone->textClasses(), 'text', $dark);
                $this->assertGreaterThanOrEqual(4.5, $this->contrast($text['rgb'], $surface), $tone->value.' text');

                $badgeText = $this->utilityColor($tone->badgeClasses(), 'text', $dark);
                $badgeBackground = $this->utilityColor($tone->badgeClasses(), 'bg', $dark);

                foreach ([$surface, $this->rgb(self::COLORS['brand'])] as $navigationSurface) {
                    $composite = array_map(
                        static fn (int|float $channel, int|float $base): float => $channel * $badgeBackground['opacity'] + $base * (1 - $badgeBackground['opacity']),
                        $badgeBackground['rgb'],
                        $navigationSurface,
                    );
                    $this->assertGreaterThanOrEqual(4.5, $this->contrast($badgeText['rgb'], $composite), $tone->value.' badge');
                }
            }
        }

        $this->assertGreaterThanOrEqual(4.5, $this->contrast($this->rgb(self::COLORS['slate-600']), $this->rgb(self::COLORS['white'])));
        $this->assertGreaterThanOrEqual(4.5, $this->contrast($this->rgb(self::COLORS['slate-400']), $this->rgb(self::COLORS['slate-900'])));
    }

    #[Test]
    public function native_progress_fills_meet_contrast_in_both_themes_without_a_browser_override(): void
    {
        $css = file_get_contents(__DIR__.'/../../../resources/css/app.css');
        $this->assertIsString($css);

        foreach (WeightBalanceComparisonTone::cases() as $tone) {
            foreach ([false, true] as $dark) {
                $selector = ($dark ? '.dark ' : '').'.'.$tone->progressClass();
                $this->assertSame(1, preg_match('/'.preg_quote($selector, '/').'\\s*\\{\\s*--cc-weight-progress-color:\\s*#([a-f0-9]{6});/', $css, $matches));
                $track = $this->rgb(self::COLORS[$dark ? 'slate-800' : 'slate-100']);
                $this->assertGreaterThanOrEqual(3.0, $this->contrast($this->rgb($matches[1]), $track), $selector);
            }
        }

        $this->assertStringContainsString('background-color: var(--cc-weight-progress-color);', $css);
        $this->assertStringContainsString('.cc-weight-progress::-webkit-progress-value', $css);
        $this->assertStringContainsString('.cc-weight-progress::-moz-progress-bar', $css);
        $this->assertStringNotContainsString('#001bfc', $css);
        $this->assertStringNotContainsString('.cc-weight-badge', $css);
    }

    #[Test]
    public function it_orders_only_operational_alert_tones_by_severity(): void
    {
        $this->assertFalse(WeightBalanceComparisonTone::Safe->isOperationalAlert());
        $this->assertTrue(WeightBalanceComparisonTone::Heavy->isOperationalAlert());
        $this->assertTrue(WeightBalanceComparisonTone::Caution->isOperationalAlert());
        $this->assertTrue(WeightBalanceComparisonTone::Exceeded->isOperationalAlert());

        $this->assertLessThan(
            WeightBalanceComparisonTone::Caution->severity(),
            WeightBalanceComparisonTone::Heavy->severity(),
        );
        $this->assertLessThan(
            WeightBalanceComparisonTone::Exceeded->severity(),
            WeightBalanceComparisonTone::Caution->severity(),
        );
    }

    /** @return array{rgb: list<int|float>, opacity: float} */
    private function utilityColor(string $classes, string $property, bool $dark): array
    {
        $this->assertSame(1, preg_match('/(?:^|\\s)'.($dark ? 'dark:' : '').$property.'-([a-z]+-\\d+)(?:\\/(\\d+))?(?=\\s|$)/', $classes, $matches));

        return [
            'rgb' => $this->rgb(self::COLORS[$matches[1]]),
            'opacity' => (float) ($matches[2] ?? 100) / 100,
        ];
    }

    /** @return list<int|float> */
    private function rgb(string $hex): array
    {
        return array_map(static fn (string $channel): int|float => hexdec($channel), str_split($hex, 2));
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
