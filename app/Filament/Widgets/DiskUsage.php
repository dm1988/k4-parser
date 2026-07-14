<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DiskUsage extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;
 
    protected function getStats(): array
    {
        // Get directory path (defaulting to the root directory)
        $path = base_path(); 

        $totalSpace = disk_total_space($path);
        $freeSpace = disk_free_space($path);
        $usedSpace = $totalSpace - $freeSpace;

        // Calculate percentage used
        $percentageUsed = $totalSpace > 0 ? round(($usedSpace / $totalSpace) * 100, 1) : 0;

        // Convert bytes to human-readable Gigabytes
        $totalGb = round($totalSpace / (1024 * 1024 * 1024), 1);
        $usedGb = round($usedSpace / (1024 * 1024 * 1024), 1);
        $freeGb = round($freeSpace / (1024 * 1024 * 1024), 1);

        $color = 'success';
        $description = "{$freeGb} GB remaining of {$totalGb} GB total";

        if ($percentageUsed >= 90) {
            $color = 'danger';
            $description = "Critical! Only {$freeGb} GB left.";
        } elseif ($percentageUsed >= 75) {
            $color = 'warning';
            $description = "Warning: Disk space filling up fast.";
        }

        return [
            Stat::make('Disk Space Used', "{$percentageUsed}%")
                ->description($description)
                ->descriptionIcon($percentageUsed >= 75 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->chart([
                    // This creates a subtle background trend line representing the split
                    $usedGb, 
                    $totalGb
                ])
                ->color($color),
        ];
    }
}