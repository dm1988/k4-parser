<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class VerifiedUserCount extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    protected int|array|null $columns = 1;

    protected function getStats(): array
    {
        return [
            Stat::make(
                'Verified Users',
                User::query()->whereNotNull('email_verified_at')->count(),
            )
                ->description('Users with verified email addresses')
                ->descriptionIcon('heroicon-o-check-badge')
                ->color('success'),
        ];
    }
}
