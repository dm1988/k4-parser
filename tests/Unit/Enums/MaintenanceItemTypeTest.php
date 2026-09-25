<?php

namespace Tests\Unit\Enums;

use App\Enums\MaintenanceItemType;
use PHPUnit\Framework\TestCase;

class MaintenanceItemTypeTest extends TestCase
{
    public function test_it_defines_the_counter_badge_priority(): void
    {
        $this->assertSame([
            MaintenanceItemType::Mel,
            MaintenanceItemType::Cdl,
            MaintenanceItemType::Nef,
            MaintenanceItemType::Dmi,
        ], MaintenanceItemType::counterPriority());
    }

    public function test_it_exposes_maintenance_titles_descriptions_and_badge_colors(): void
    {
        $this->assertSame('Minimum Equipment List', MaintenanceItemType::Mel->title());
        $this->assertSame('Configuration Deviation List', MaintenanceItemType::Cdl->title());
        $this->assertSame('Deferred Maintenance Item', MaintenanceItemType::Dmi->title());
        $this->assertSame('Non-Essential Equipment & Furnishings', MaintenanceItemType::Nef->title());

        foreach (MaintenanceItemType::cases() as $type) {
            $this->assertNotSame('', $type->description());
            $this->assertStringContainsString('bg-', $type->badgeColor());
            $this->assertStringContainsString('dark:', $type->badgeColor());
        }

        $this->assertSame(
            'bg-gray-100 text-gray-900 dark:bg-gray-700 dark:text-gray-100',
            MaintenanceItemType::Nef->badgeColor(),
        );
        $this->assertSame('text-red-700 dark:text-red-300', MaintenanceItemType::Mel->metricTextColor());
        $this->assertSame('text-orange-700 dark:text-orange-300', MaintenanceItemType::Cdl->metricTextColor());
        $this->assertSame('text-gray-700 dark:text-gray-300', MaintenanceItemType::Nef->metricTextColor());
        $this->assertSame('text-yellow-700 dark:text-yellow-300', MaintenanceItemType::Dmi->metricTextColor());
    }
}
