<?php

namespace Tests\Unit\View\Models;

use App\Enums\ParserEventType;
use App\View\Models\Parser\ParserPageViewModel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ParserPageViewModelTest extends TestCase
{
    #[Test]
    public function it_uses_old_input_to_build_form_state(): void
    {
        $viewModel = ParserPageViewModel::fromSession([
            'filters' => ['flight'],
        ], [
            'event_types' => ['layover'],
            'text' => 'Pasted roster text',
        ]);

        $this->assertSame(['layover'], $viewModel->selectedTypes);
        $this->assertSame('Pasted roster text', $viewModel->text);
    }

    #[Test]
    public function it_formats_result_events_for_the_view(): void
    {
        $viewModel = ParserPageViewModel::fromSession([
            'source' => 'pdf',
            'filters' => ['flight'],
            'parse_key' => '01JTESTPARSEKEYABC123',
            'parsed' => [
                'trip' => ['trip_number' => '1234'],
                'calendar_events' => [[
                    'title' => 'ICN - NRT (K4123)',
                    'type' => 'flight',
                    'start' => '2026-06-15 09:00:00',
                    'end' => '2026-06-15 11:30:00',
                    'download_id' => '01JTESTEVENTKEYABC123',
                    'metadata' => [
                        'tail_number' => 'hl1234',
                        'deadhead' => true,
                    ],
                ]],
            ],
        ]);

        $this->assertTrue($viewModel->hasResult());
        $this->assertNotNull($viewModel->result);
        $this->assertSame('Pdf', $viewModel->result->sourceLabel);
        $this->assertSame('1234', $viewModel->result->tripNumber);
        $this->assertSame(1, $viewModel->result->eventCount);
        $this->assertSame('01JTESTPARSEKEYABC123', $viewModel->result->parseKey);
        $this->assertSame(route('parse.export', ['event_types' => ['flight'], 'parse_key' => '01JTESTPARSEKEYABC123']), $viewModel->result->exportUrl);
        $this->assertCount(1, $viewModel->result->events);
        $this->assertSame('flight', $viewModel->result->events[0]->type);
        $this->assertSame('Flight', $viewModel->result->events[0]->typeLabel);
        $this->assertSame('heroicon-o-paper-airplane', $viewModel->result->events[0]->typeIcon);
        $this->assertSame('Jun 15 • 9:00 AM - 11:30 AM', $viewModel->result->events[0]->scheduleLabel);
        $this->assertSame('2h 30m', $viewModel->result->events[0]->durationLabel);
        $this->assertSame('HL1234', $viewModel->result->events[0]->tailNumber);
        $this->assertTrue($viewModel->result->events[0]->isDeadhead);
        $this->assertSame(
            route('parse.export.event', ['eventId' => '01JTESTEVENTKEYABC123', 'parse_key' => '01JTESTPARSEKEYABC123']),
            $viewModel->result->events[0]->downloadUrl
        );
    }

    #[Test]
    public function it_marks_error_results(): void
    {
        $viewModel = ParserPageViewModel::fromSession([
            'error' => 'Roster text resolution failed.',
        ]);

        $this->assertTrue($viewModel->hasResult());
        $this->assertNotNull($viewModel->result);
        $this->assertTrue($viewModel->result->hasError());
        $this->assertSame('Roster text resolution failed.', $viewModel->result->errorMessage);
        $this->assertNull($viewModel->result->exportUrl);
    }

    #[Test]
    public function it_builds_filter_options_from_the_event_type_enum(): void
    {
        $viewModel = ParserPageViewModel::fromSession(null);

        $this->assertSame(ParserEventType::filterValues(), array_column($viewModel->filterOptions, 'value'));
        $this->assertSame('Flights only', $viewModel->filterOptions[0]['label']);
        $this->assertSame('Scheduled flying segment.', $viewModel->filterOptions[0]['description']);
    }
}
