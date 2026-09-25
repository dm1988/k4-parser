<?php

namespace Tests\Unit\View\Models;

use App\DTOs\WeightBalance\WeightBalanceFieldData;
use App\Enums\WeightBalanceSourceStatus;
use App\ValueObjects\WeightQuantity;
use App\View\Models\FlightRelease\WeightBalanceFieldViewModel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WeightBalanceFieldViewModelTest extends TestCase
{
    #[Test]
    public function it_prepares_an_integrated_limit_for_rendering(): void
    {
        $viewModel = new WeightBalanceFieldViewModel(
            label: 'Takeoff gross weight',
            field: new WeightBalanceFieldData(
                plannedValue: WeightQuantity::pounds(577347),
                sourceStatus: WeightBalanceSourceStatus::Confirmed,
                permittedLimit: WeightQuantity::pounds(580000),
                limitStatus: WeightBalanceSourceStatus::Confirmed,
            ),
            comparesToLimit: true,
            integratesLimitInProgress: true,
        );

        $this->assertFalse($viewModel->showsSourceStatusBadge());
        $this->assertSame('577,347', $viewModel->plannedAmountLabel());
        $this->assertSame('LB', $viewModel->plannedUnit());
        $this->assertFalse($viewModel->showsStandaloneLimit());
        $this->assertSame('', $viewModel->valueLayoutClasses());
        $this->assertTrue($viewModel->hasUtilizationComparison());
        $this->assertTrue($viewModel->integratesLimitWithinProgress());
        $this->assertSame(99.5, $viewModel->progressValue());
        $this->assertSame('99.5% of structural limit', $viewModel->utilizationLabel());
        $this->assertSame('99.5% OF 580,000 LB LIMIT', $viewModel->progressOverlayLabel());
        $this->assertSame('99.5% of 580,000 LB limit', $viewModel->utilizationAriaValueText());
        $this->assertSame('cc-weight-progress-caution', $viewModel->comparisonProgressClass());
    }

    #[Test]
    public function it_clamps_an_exceeded_limit_for_the_progress_element(): void
    {
        $viewModel = new WeightBalanceFieldViewModel(
            label: 'Estimated landing weight',
            field: new WeightBalanceFieldData(
                plannedValue: WeightQuantity::pounds(371893),
                sourceStatus: WeightBalanceSourceStatus::Confirmed,
                permittedLimit: WeightQuantity::pounds(370000),
                limitStatus: WeightBalanceSourceStatus::Confirmed,
            ),
            comparesToLimit: true,
            integratesLimitInProgress: true,
        );

        $this->assertSame(100.0, $viewModel->progressValue());
        $this->assertSame('Structural limit exceeded', $viewModel->comparisonLabel());
        $this->assertSame('cc-weight-progress-exceeded', $viewModel->comparisonProgressClass());
    }

    #[Test]
    public function it_prepares_source_status_and_unavailable_comparisons(): void
    {
        $conflict = new WeightBalanceFieldViewModel(
            label: 'Payload',
            field: new WeightBalanceFieldData(
                plannedValue: null,
                sourceStatus: WeightBalanceSourceStatus::Conflict,
            ),
        );
        $unitMismatch = new WeightBalanceFieldViewModel(
            label: 'Zero-fuel weight',
            field: new WeightBalanceFieldData(
                plannedValue: WeightQuantity::pounds(350000),
                sourceStatus: WeightBalanceSourceStatus::Confirmed,
                permittedLimit: WeightQuantity::kilograms(200000),
                limitStatus: WeightBalanceSourceStatus::Confirmed,
            ),
            comparesToLimit: true,
        );

        $this->assertTrue($conflict->showsSourceStatusBadge());
        $this->assertSame('Conflict', $conflict->plannedAmountLabel());
        $this->assertNull($conflict->plannedUnit());
        $this->assertStringContainsString('bg-red-100', $conflict->sourceStatus->badgeClasses());
        $this->assertFalse($unitMismatch->hasUtilizationComparison());
        $this->assertSame('Comparison unavailable: units differ', $unitMismatch->comparisonUnavailableLabel());
        $this->assertTrue($unitMismatch->showsStandaloneLimit());
        $this->assertSame('grid grid-cols-2 gap-4', $unitMismatch->valueLayoutClasses());
    }
}
