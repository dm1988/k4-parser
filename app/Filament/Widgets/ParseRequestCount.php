<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ParseRequestCount extends StatsOverviewWidget
{
    protected static ?int $sort = 3;
    protected int|string|array $columnSpan = 1;

    protected function getStats(): array
    {
        return [
            Stat::make('Total Parse Requests', \App\Models\ParseRequest::count())
                ->description('Total number of parse requests')
                ->descriptionIcon('heroicon-o-bolt'),
        ];
    }
}
