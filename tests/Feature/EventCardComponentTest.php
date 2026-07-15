<?php

namespace Tests\Feature;

use App\View\Models\Parser\ParserEventViewModel;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class EventCardComponentTest extends TestCase
{
    public function test_it_renders_date_only_headers_and_utc_schedule_labels_for_layovers(): void
    {
        $html = Blade::render('<x-parser.event-card :event="$event" />', [
            'event' => ParserEventViewModel::fromArray([
                'title' => 'Layover ICN',
                'type' => 'layover',
                'start' => '2026-07-02T23:59:00+00:00',
                'end' => '2026-07-04T09:00:00+00:00',
                'metadata' => [],
                'download_id' => '01JTESTEVENTKEYABC123',
            ], '01JTESTPARSEKEYABC123'),
        ]);

        $this->assertStringContainsString('Layover ICN', $html);
        $this->assertStringContainsString('Jul 2', $html);
        $this->assertStringContainsString('Jul 2, 2359 Z -&gt; Jul 4, 0900 Z', $html);
        $this->assertStringNotContainsString('Jul 2, 11:59 PM -&gt; Jul 4, 9:00 AM', $html);
    }
}
