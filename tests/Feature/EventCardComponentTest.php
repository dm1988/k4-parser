<?php

namespace Tests\Feature;

use App\Mappers\DutyEventMapper;
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

    public function test_it_renders_utc_schedule_labels_for_duty_cards(): void
    {
        $html = Blade::render('<x-parser.event-card :event="$event" />', [
            'event' => app(DutyEventMapper::class)->fromCalendarEvent([
                'title' => 'Duty CVG',
                'type' => 'duty',
                'start' => '2026-07-01T20:45:00+00:00',
                'end' => '2026-07-01T22:14:00+00:00',
                'download_id' => '01JTESTEVENTKEYABC123',
                'metadata' => [
                    'station' => 'CVG',
                ],
            ], '01JTESTEVENTKEYABC123'),
        ]);

        $this->assertStringContainsString('Duty CVG', $html);
        $this->assertStringContainsString('Jul 1', $html);
        $this->assertStringContainsString('Jul 1 • 2045 Z - 2214 Z', $html);
        $this->assertStringNotContainsString('Jul 1 • 8:45 PM - 10:14 PM', $html);
    }
}
