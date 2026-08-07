<?php

namespace App\Services\Infrastructure;

class DiskSpaceMonitor
{
    public function freePercentage(string $path): ?float
    {
        $freeSpace = disk_free_space($path);
        $totalSpace = disk_total_space($path);

        if ($freeSpace === false || $totalSpace === false || $totalSpace <= 0) {
            return null;
        }

        return ($freeSpace / $totalSpace) * 100;
    }
}
