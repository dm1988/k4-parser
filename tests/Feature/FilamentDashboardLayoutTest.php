<?php

namespace Tests\Feature;

use App\Filament\Widgets\DiskUsage;
use App\Filament\Widgets\ExtractRequestCount;
use App\Filament\Widgets\ExtractRequestsPerDayChart;
use App\Filament\Widgets\UserCount;
use App\Filament\Widgets\VerifiedUserCount;
use Filament\Widgets\StatsOverviewWidget;
use Tests\TestCase;

class FilamentDashboardLayoutTest extends TestCase
{
    public function test_each_stat_fills_its_widget_column(): void
    {
        $widgets = [
            new UserCount,
            new VerifiedUserCount,
            new DiskUsage,
            new ExtractRequestCount,
        ];

        foreach ($widgets as $widget) {
            $this->assertSame(['lg' => 1], $this->columnsFor($widget));
            $this->assertSame(1, $widget->getColumnSpan());
        }
    }

    public function test_extract_request_chart_spans_the_full_dashboard_width(): void
    {
        $this->assertSame('full', (new ExtractRequestsPerDayChart)->getColumnSpan());
    }

    private function columnsFor(StatsOverviewWidget $widget): int|array|null
    {
        return $widget->getSectionContentComponent()->getColumns();
    }
}
