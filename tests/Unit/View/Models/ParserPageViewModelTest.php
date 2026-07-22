<?php

namespace Tests\Unit\View\Models;

use App\DTOs\DutyEvent;
use App\DTOs\Flight;
use App\DTOs\ParserResultData;
use App\Enums\ParserEventType;
use App\Services\ParserResultCache;
use App\View\Models\Parser\ParserPageViewModel;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ParserPageViewModelTest extends TestCase
{
    #[Test]
    public function it_builds_selected_types_without_legacy_old_input(): void
    {
        $viewModel = ParserPageViewModel::fromResult(ParserResultData::fromArray([
            'filters' => ['flight'],
        ]));

        $this->assertSame(['flight'], $viewModel->selectedTypes);
    }

    #[Test]
    public function it_formats_result_events_for_the_view(): void
    {
        $viewModel = ParserPageViewModel::fromResult(ParserResultData::fromArray([
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
        ]));

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
    public function it_adds_parse_scoped_export_urls_to_flight_dtos(): void
    {
        $viewModel = ParserPageViewModel::fromResult(ParserResultData::fromArray([
            'source' => 'text',
            'filters' => [],
            'parse_key' => '01JTESTPARSEKEYABC123',
            'parsed' => [
                'trip' => ['trip_number' => '1234'],
                'calendar_events' => [
                    Flight::fromArray([
                        'title' => 'CKS 271 ICN-ANC',
                        'type' => 'flight',
                        'typeLabel' => 'Flight',
                        'typeDescription' => 'Scheduled flying segment.',
                        'scheduleLabel' => 'Jun 26, 11:45 PM -> Jun 27, 8:00 AM',
                        'durationLabel' => '8:15h',
                        'isDeadhead' => false,
                        'badgeColor' => 'bg-blue-100 text-blue-900',
                        'downloadUrl' => 'https://www.flightaware.com/live/flight/N773CK',
                        'downloadId' => '01JTESTEVENTKEYABC123',
                        'flightNumber' => 'CKS 271',
                        'legLocalStart' => 'Jun 26 19:45',
                        'legLocalEnd' => 'Jun 27 05:00',
                        'dutyLocalStart' => 'Jun 26 17:45',
                        'dutyLocalEnd' => 'Jun 27 10:40',
                        'start' => '2026-06-26T23:45:00+00:00',
                        'end' => '2026-06-27T08:00:00+00:00',
                        'origin' => 'ICN',
                        'destination' => 'ANC',
                    ]),
                ],
            ],
        ]));

        $this->assertSame(
            route('parse.export.event', ['eventId' => '01JTESTEVENTKEYABC123', 'parse_key' => '01JTESTPARSEKEYABC123']),
            $viewModel->result->events[0]->downloadUrl
        );
    }

    #[Test]
    public function it_adds_parse_scoped_export_urls_to_duty_event_dtos(): void
    {
        $viewModel = ParserPageViewModel::fromResult(ParserResultData::fromArray([
            'source' => 'text',
            'filters' => [],
            'parse_key' => '01JTESTPARSEKEYABC123',
            'parsed' => [
                'trip' => ['trip_number' => '1234'],
                'calendar_events' => [
                    DutyEvent::fromArray([
                        'title' => 'Hotel Check-In',
                        'type' => 'duty',
                        'start' => '2026-06-13T14:00:00+00:00',
                        'end' => '2026-06-13T16:00:00+00:00',
                        'download_id' => '01JTESTEVENTKEYABC123',
                    ]),
                ],
            ],
        ]));

        $this->assertInstanceOf(DutyEvent::class, $viewModel->result->events[0]);
        $this->assertSame(
            route('parse.export.event', ['eventId' => '01JTESTEVENTKEYABC123', 'parse_key' => '01JTESTPARSEKEYABC123']),
            $viewModel->result->events[0]->downloadUrl,
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

        $viewModel = ParserPageViewModel::fromResult(app(ParserResultCache::class)->latest());

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
        $viewModel = ParserPageViewModel::fromResult(ParserResultData::fromArray([
            'error' => 'Roster text resolution failed.',
        ]));

        $this->assertTrue($viewModel->hasResult());
        $this->assertNotNull($viewModel->result);
        $this->assertTrue($viewModel->result->hasError());
        $this->assertSame('Roster text resolution failed.', $viewModel->result->errorMessage);
        $this->assertNull($viewModel->result->exportUrl);
    }

    #[Test]
    public function it_builds_filter_options_from_the_event_type_enum(): void
    {
        $viewModel = ParserPageViewModel::fromResult(null);

        $this->assertSame([], $viewModel->selectedTypes);
        $this->assertSame(ParserEventType::filterValues(), array_column($viewModel->filterOptions, 'value'));
        $this->assertSame('Flights only', $viewModel->filterOptions[0]['label']);
        $this->assertSame('Scheduled flying segment.', $viewModel->filterOptions[0]['description']);
    }
}
