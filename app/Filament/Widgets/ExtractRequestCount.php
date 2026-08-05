<?php

namespace App\Filament\Widgets;

use App\Models\ExtractRequest;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ExtractRequestCount extends StatsOverviewWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    protected int|array|null $columns = 1;

    protected function getStats(): array
    {
        return [
            Stat::make('Total Extract Requests', ExtractRequest::count())
                ->description('Total number of extract requests')
                ->descriptionIcon('heroicon-o-bolt'),
        ];
    }
}
