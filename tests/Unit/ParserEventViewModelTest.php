<?php

namespace Tests\Unit;

use App\View\Models\Parser\ParserEventViewModel;
use Tests\TestCase;

class ParserEventViewModelTest extends TestCase
{
    public function test_it_formats_layover_header_and_schedule_labels_for_event_cards(): void
    {
        $model = ParserEventViewModel::fromArray([
            'title' => 'Layover ICN',
            'type' => 'layover',
            'start' => '2026-07-02T23:59:00+00:00',
            'end' => '2026-07-04T09:00:00+00:00',
            'metadata' => [],
            'download_id' => '01JTESTEVENTKEYABC123',
        ], '01JTESTPARSEKEYABC123');

        $this->assertSame('Jul 2', $model->headingDateLabel());
        $this->assertSame('Jul 2, 2359 Z -> Jul 4, 0900 Z', $model->scheduleLabel);
        $this->assertSame('33h 1m', $model->durationLabel);
    }
}
