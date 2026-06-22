<?php

namespace Tests\Unit\View\Models;

use App\Enums\ParserEventType;
use App\View\Models\Parser\ParserPageViewModel;
use Illuminate\Support\Facades\Cache;
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
            'event_types' => ['flight'],
            'text' => 'Pasted roster text',
        ]);

        $this->assertSame(['flight'], $viewModel->selectedTypes);
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
        $this->assertCount(1, $viewModel->result->events);
        $this->assertSame('01JTESTPARSEKEYABC123', $viewModel->result->parseKey);
        $this->assertSame(
            route('parse.export', ['event_types' => [ParserEventType::Flight->value], 'parse_key' => '01JTESTPARSEKEYABC123']),
            $viewModel->result->exportUrl
        );
        $this->assertCount(1, $viewModel->result->events);
        $this->assertSame(ParserEventType::Deadhead->value, $viewModel->result->events[0]->type);
        $this->assertSame(ParserEventType::Deadhead->label(), $viewModel->result->events[0]->typeLabel);
        $this->assertSame(ParserEventType::Deadhead->icon(), $viewModel->result->events[0]->typeIcon);
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
    public function it_hydrates_the_full_result_from_cache_when_session_only_has_a_trimmed_payload(): void
    {
        session([
            'latest_parse_key' => '01JTESTPARSEKEYABC123',
            'parsed_results_namespace' => '01JTESTSESSIONKEYABC123',
        ]);

        Cache::put('sessions:01JTESTSESSIONKEYABC123:parsed_results:01JTESTPARSEKEYABC123', [
            'type' => 'roster',
            'source' => 'pdf',
            'filters' => [],
            'parse_key' => '01JTESTPARSEKEYABC123',
            'parsed' => [
                'trip' => ['trip_number' => '13131'],
                'calendar_events' => [[
                    'title' => 'CVG - NRT (206)',
                    'type' => 'flight',
                    'start' => '2026-06-13T06:38:00+00:00',
                    'end' => '2026-06-13T08:31:00+00:00',
                    'download_id' => '01JTESTEVENTKEYABC123',
                    'metadata' => [
                        'origin' => 'CVG',
                        'destination' => 'NRT',
                    ],
                ]],
            ],
        ]);

        $viewModel = ParserPageViewModel::fromCurrentSession();

        $this->assertTrue($viewModel->hasResult());
        $this->assertNotNull($viewModel->result);
        $this->assertSame('Pdf', $viewModel->result->sourceLabel);
        $this->assertSame('13131', $viewModel->result->tripNumber);
        $this->assertSame(1, $viewModel->result->eventCount);
        $this->assertSame('CVG - NRT (206)', $viewModel->result->events[0]->title);
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
