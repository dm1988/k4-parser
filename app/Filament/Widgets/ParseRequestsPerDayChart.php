<?php

namespace App\Filament\Widgets;

use App\Models\ParseRequest;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class ParseRequestsPerDayChart extends ChartWidget
{
    protected static ?int $sort = 4;
    protected int|string|array $columnSpan = 1;

    protected ?string $heading = 'Parse Requests Per Day';

    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $startDate = CarbonImmutable::today()->subDays(6);
        $requestCounts = ParseRequest::query()
            ->selectRaw('DATE(created_at) as request_date, COUNT(*) as aggregate')
            ->where('created_at', '>=', $startDate->startOfDay())
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy(DB::raw('DATE(created_at)'))
            ->pluck('aggregate', 'request_date');

        $dates = collect(range(0, 6))
            ->map(fn (int $dayOffset): CarbonImmutable => $startDate->addDays($dayOffset));

        return [
            'datasets' => [
                [
                    'label' => 'Parse requests',
                    'data' => $dates
                        ->map(fn (CarbonImmutable $date): int => (int) ($requestCounts[$date->toDateString()] ?? 0))
                        ->all(),
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.15)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $dates
                ->map(fn (CarbonImmutable $date): string => $date->format('M j'))
                ->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
