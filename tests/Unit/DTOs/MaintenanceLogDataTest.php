<?php

namespace Tests\Unit\DTOs;

use App\DTOs\MaintenanceItemData;
use App\DTOs\MaintenanceLogData;
use App\Enums\EtopsApplicability;
use App\Enums\MaintenanceItemType;
use PHPUnit\Framework\TestCase;

class MaintenanceLogDataTest extends TestCase
{
    public function test_it_serializes_typed_items_without_shared_crew_or_source_fragments(): void
    {
        $data = new MaintenanceLogData(
            sectionPresent: true,
            etopsApplicability: EtopsApplicability::ConfirmedEtops,
            items: [
                new MaintenanceItemData(
                    type: MaintenanceItemType::Mel,
                    number: '28-22-01',
                    description: 'Center tank override pump inoperative.',
                    reference: '1042',
                    status: 'OPEN',
                ),
            ],
        );

        $this->assertSame('confirmed_etops', $data->toArray()['etopsApplicability']);
        $this->assertSame('MEL', $data->toArray()['items'][0]['type']);
        $this->assertArrayNotHasKey('crewMembers', $data->toArray());
        $this->assertArrayNotHasKey('source', $data->toArray()['items'][0]);
    }
}
