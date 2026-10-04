<?php

namespace Tests\Feature;

use App\DTOs\WeightBalance\WeightBalanceFieldData;
use App\Enums\WeightBalanceComparisonTone;
use App\Enums\WeightBalanceSourceStatus;
use App\ValueObjects\WeightQuantity;
use App\View\Models\FlightRelease\WeightBalanceFieldViewModel;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WeightBalanceFieldComponentTest extends TestCase
{
    #[DataProvider('comparisonStates')]
    public function test_integrated_fields_keep_labels_outside_an_accessible_native_bar(int $amount, string $percent, WeightBalanceComparisonTone $tone): void
    {
        $field = $this->field(WeightQuantity::pounds($amount), WeightQuantity::pounds(100000), integrated: true);
        $html = Blade::render('<x-flight-release.weight-balance-field :field="$field" class="flex-1" />', ['field' => $field]);

        $this->assertSame(1, substr_count($html, '<progress'));
        $this->assertStringContainsString('cc-weight-progress block h-2 w-full '.$tone->progressClass(), $html);
        $this->assertStringContainsString('aria-label="Zero-fuel weight utilization"', $html);
        $this->assertStringContainsString('aria-valuetext="'.$percent.'% of 100,000 LB limit"', $html);
        $this->assertStringContainsString('value="'.min((float) $percent, 100).'"', $html);
        $this->assertStringContainsString($tone->textClasses(), $html);
        $this->assertStringContainsString('flex-1', $html);
        $this->assertStringNotContainsString('absolute', $html);
        $this->assertStringNotContainsString('truncate', $html);
        $this->assertStringNotContainsString('transition', $html);
        $this->assertStringNotContainsString('animate-', $html);
        $this->assertStringContainsString('cc-weight-percentage-header', $html);
        $this->assertMatchesRegularExpression('/cc-weight-percentage-above-bar[^>]*>\s*'.preg_quote($percent.'% of limit', '/').'\s*<\/p>\s*<progress/s', $html);
        $this->assertSame(1, preg_match('/<progress[^>]*>(.*?)<\\/progress>/s', $html, $matches));
        $this->assertStringNotContainsString('<span', $matches[1]);

        $cursor = 0;
        foreach (['Zero-fuel weight', $percent.'% of limit', $field->plannedAmountLabel(), '<progress', '</progress>', 'Max limit: 100,000 LB', $tone->label()] as $text) {
            $position = strpos($html, $text, $cursor);
            $this->assertNotFalse($position, $text.' must follow the preceding content');
            $cursor = $position + strlen($text);
        }
    }

    /** @return array<string, array{int, string, WeightBalanceComparisonTone}> */
    public static function comparisonStates(): array
    {
        return [
            'normal' => [88500, '88.5', WeightBalanceComparisonTone::Safe],
            'heavy' => [96600, '96.6', WeightBalanceComparisonTone::Heavy],
            'caution' => [99500, '99.5', WeightBalanceComparisonTone::Caution],
            'exceeded' => [100500, '100.5', WeightBalanceComparisonTone::Exceeded],
            'confirmed zero' => [0, '0.0', WeightBalanceComparisonTone::Safe],
        ];
    }

    public function test_standalone_limits_keep_their_value_and_unit_without_duplicate_metadata(): void
    {
        $field = $this->field(WeightQuantity::kilograms(99500), WeightQuantity::kilograms(100000));
        $html = Blade::render('<x-flight-release.weight-balance-field :field="$field" />', ['field' => $field]);

        $this->assertStringContainsString('Structural limit', $html);
        $this->assertStringContainsString('100,000', $html);
        $this->assertStringContainsString('99.5% of 100,000 KG limit', $html);
        $this->assertStringContainsString('Near structural limit', $html);
        $this->assertStringNotContainsString('Max limit:', $html);
    }

    public function test_unavailable_comparisons_never_render_a_bar_or_normal_status(): void
    {
        $fields = [
            [$this->field(WeightQuantity::pounds(88500), null, integrated: true), 'Limit unavailable'],
            [$this->field(WeightQuantity::pounds(88500), WeightQuantity::kilograms(100000), integrated: true), 'Comparison unavailable: units differ'],
            [$this->field(null, null, WeightBalanceSourceStatus::NotPresent, integrated: true), 'Not present'],
            [$this->field(null, null, WeightBalanceSourceStatus::Conflict, integrated: true), 'Conflict'],
            [$this->field(WeightQuantity::pounds(88500), WeightQuantity::pounds(0), integrated: true), '88,500'],
        ];

        foreach ($fields as [$field, $message]) {
            $html = Blade::render('<x-flight-release.weight-balance-field :field="$field" />', ['field' => $field]);

            $this->assertStringContainsString($message, $html);
            $this->assertStringNotContainsString('<progress', $html);
            $this->assertStringNotContainsString('% of limit', $html);
            $this->assertStringNotContainsString('Within operating margin', $html);
            $this->assertStringNotContainsString('Max limit:', $html);
        }
    }

    private function field(
        ?WeightQuantity $planned,
        ?WeightQuantity $limit,
        WeightBalanceSourceStatus $sourceStatus = WeightBalanceSourceStatus::Confirmed,
        bool $integrated = false,
    ): WeightBalanceFieldViewModel {
        return new WeightBalanceFieldViewModel(
            label: 'Zero-fuel weight',
            field: new WeightBalanceFieldData(
                plannedValue: $planned,
                sourceStatus: $sourceStatus,
                permittedLimit: $limit,
                limitStatus: $limit === null ? WeightBalanceSourceStatus::LimitUnavailable : WeightBalanceSourceStatus::Confirmed,
            ),
            comparesToLimit: true,
            integratesLimitInProgress: $integrated,
        );
    }
}
