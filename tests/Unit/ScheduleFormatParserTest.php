<?php

namespace Tests\Unit;

use App\Enums\ScheduleDocumentType;
use App\Services\Schedule\Extractor\PublishedRosterParser;
use App\Services\Schedule\Extractor\ScheduleFormatParser;
use App\Services\Schedule\Extractor\TripInformationParser;
use Mockery\MockInterface;
use Tests\TestCase;

class ScheduleFormatParserTest extends TestCase
{
    public function test_schedule_document_types_define_their_parser_type(): void
    {
        $this->assertSame('trip_pdf', ScheduleDocumentType::TripInformation->parserType());
        $this->assertSame('roster_pdf', ScheduleDocumentType::PublishedRoster->parserType());
    }

    public function test_it_dispatches_trip_information_text_to_the_trip_information_parser(): void
    {
        $expected = ['trip' => ['trip_number' => '13131'], 'calendar_events' => []];

        $this->mock(TripInformationParser::class, function (MockInterface $mock) use ($expected): void {
            $mock->shouldReceive('parse')
                ->once()
                ->with('Trip Information text')
                ->andReturn($expected);
        });

        $result = app(ScheduleFormatParser::class)->parse(
            'Trip Information text',
            ScheduleDocumentType::TripInformation->value,
        );

        $this->assertSame($expected, $result);
    }

    public function test_it_dispatches_published_roster_text_to_the_published_roster_parser(): void
    {
        $expected = ['trip' => [], 'calendar_events' => []];

        $this->mock(PublishedRosterParser::class, function (MockInterface $mock) use ($expected): void {
            $mock->shouldReceive('parse')
                ->once()
                ->with('Published Roster text')
                ->andReturn($expected);
        });

        $result = app(ScheduleFormatParser::class)->parse(
            'Published Roster text',
            ScheduleDocumentType::PublishedRoster->value,
        );

        $this->assertSame($expected, $result);
    }
}
