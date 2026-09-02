<?php

namespace Tests\Unit\DTOs\Maintenance;

use App\DTOs\Maintenance\MaintenanceItemData;
use App\DTOs\Maintenance\MaintenanceLogData;
use App\Enums\MaintenanceItemType;
use PHPUnit\Framework\TestCase;

class MaintenanceLogDataTest extends TestCase
{
    public function test_it_serializes_typed_maintenance_items_without_unrelated_etops_state(): void
    {
        $data = new MaintenanceLogData(
            sectionPresent: true,
            items: [
                new MaintenanceItemData(
                    type: MaintenanceItemType::Mel,
                    number: '28-22-01',
                    description: 'Center tank override pump inoperative.',
                ),
            ],
        );

        $this->assertTrue($data->toArray()['sectionPresent']);
        $this->assertSame('28-22-01', $data->toArray()['items'][0]['number']);
        $this->assertArrayNotHasKey('etopsApplicability', $data->toArray());
        $this->assertSame($data->toArray(), $data->jsonSerialize());
    }
}
