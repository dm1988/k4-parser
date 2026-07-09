<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UserCount extends StatsOverviewWidget
{
    protected static ?int $sort = 2;
    protected function getStats(): array
    {
        return [
            Stat::make('Total Users', User::count())
                ->description('Total number of registered users')
                ->descriptionIcon('heroicon-o-users'),
        ];
    }
}
