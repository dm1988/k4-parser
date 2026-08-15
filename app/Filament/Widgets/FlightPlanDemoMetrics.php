<?php

namespace App\Filament\Widgets;

use App\Models\ExtractRequest;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FlightPlanDemoMetrics extends StatsOverviewWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Flight Plan Demo';

    protected ?string $description = 'Lifetime usage recorded by the flight-plan extractor.';

    protected function getStats(): array
    {
        /** @var object{request_count: int|string, failure_count: int|string|null, average_duration_ms: float|int|string|null, user_count: int|string} $metrics */
        $metrics = ExtractRequest::query()
            ->where('parser_type', 'flight_plan')
            ->selectRaw(
                'COUNT(*) as request_count,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failure_count,
                AVG(CASE WHEN status IN (?, ?) THEN extraction_duration_ms ELSE NULL END) as average_duration_ms,
                COUNT(DISTINCT user_id) as user_count',
                ['failed', 'success', 'failed'],
            )
            ->toBase()
            ->first();

        $requestCount = (int) $metrics->request_count;
        $failureCount = (int) ($metrics->failure_count ?? 0);
        $averageDurationMs = (int) round((float) ($metrics->average_duration_ms ?? 0));
        $userCount = (int) $metrics->user_count;
        $failureRate = $requestCount > 0 ? ($failureCount / $requestCount) * 100 : 0;

        return [
            Stat::make('Flight Plan Requests', $requestCount)
                ->description('All logged demo uploads')
                ->descriptionIcon('heroicon-o-document-arrow-up'),
            Stat::make('Failed Extracts', $failureCount)
                ->description(sprintf('%.1f%% failure rate', $failureRate))
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color($failureCount > 0 ? 'danger' : 'success'),
            Stat::make('Average Processing Time', "{$averageDurationMs} ms")
                ->description('Completed and failed requests')
                ->descriptionIcon('heroicon-o-clock'),
            Stat::make('Users Adopted', $userCount)
                ->description('Distinct users who tried the demo')
                ->descriptionIcon('heroicon-o-users')
                ->color('success'),
        ];
    }
}
