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
        $this->assertSame(
            'border-red-500/30 border-l-4 border-l-red-500 bg-red-500/5 backdrop-blur dark:border-red-400/30 dark:border-l-red-400 dark:bg-red-400/10',
            MaintenanceItemType::Mel->overviewCardClasses(),
        );
        $this->assertSame(
            'border-orange-500/30 border-l-4 border-l-orange-500 bg-orange-500/5 backdrop-blur dark:border-orange-400/30 dark:border-l-orange-400 dark:bg-orange-400/10',
            MaintenanceItemType::Cdl->overviewCardClasses(),
        );
        $this->assertSame(
            'border-gray-500/30 border-l-4 border-l-gray-500 bg-gray-500/5 backdrop-blur dark:border-gray-400/30 dark:border-l-gray-400 dark:bg-gray-400/10',
            MaintenanceItemType::Nef->overviewCardClasses(),
        );
        $this->assertSame(
            'border-yellow-500/30 border-l-4 border-l-yellow-500 bg-yellow-500/5 backdrop-blur dark:border-yellow-400/30 dark:border-l-yellow-400 dark:bg-yellow-400/10',
            MaintenanceItemType::Dmi->overviewCardClasses(),
        );
    }
}
