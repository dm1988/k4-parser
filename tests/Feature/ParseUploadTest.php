<?php

namespace Tests\Feature;

use App\Enums\ScheduleDocumentType;
use App\Livewire\ScheduleExtractor;
use App\Models\ParseRequest;
use App\Models\User;
use App\Services\Schedule\Extractor\ScheduleFormatParser;
use App\Services\Schedule\ScheduleInputResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class ParseUploadTest extends TestCase
{
    public function test_parse_page_renders_centered_dropzone_and_disabled_extract_button(): void
    {
        $page = $this->actingAs(User::factory()->make())->get(route('parse.index'));
        $content = $page->getContent();

        $page->assertOk();
        $page->assertSee('wire:submit="parseRoster"', false);
        $page->assertSee('wire:model="file"', false);
        $page->assertSeeText('Drop your schedule here');
        $page->assertSeeText('Supports PDF and all image formats. Click to browse your files.');
        $page->assertSee('wire:loading.attr="disabled"', false);
        $page->assertSee('data-parse-submit', false);
        $page->assertSee('disabled:bg-[#1B365D]/10', false);
        $page->assertSee('disabled', false);
        $page->assertDontSee('class="cc-card overflow-hidden"', false);
        $page->assertDontSee('shadow-lg shadow-[#1B365D]/10', false);
        $page->assertSee('text-[#1B365D] md:text-5xl', false);
        $page->assertSee('text-base leading-relaxed text-[#4A5568]', false);
        $page->assertSeeInOrder([
            'Extract Schedule',
            'Not sure where to start?',
            'View the workflow guide',
        ]);
        $page->assertDontSee('x-data="parserForm()"', false);
        $page->assertDontSee("const parserForm = document.getElementById('parserForm');", false);
        $page->assertSee('wire:name="schedule-extractor"', false);
        $this->assertSame(1, substr_count($content, '<main'));
        $this->assertSame(substr_count($content, '<main'), substr_count($content, '</main>'));
    }

    public function test_dashboard_and_parse_routes_use_the_same_parser_page_composition(): void
    {
        $user = User::factory()->make();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewIs('dashboard')
            ->assertSee('wire:name="schedule-extractor"', false)
            ->assertSeeText('Jeppesen Crew Access')
            ->assertSeeText('Schedule Extractor')
            ->assertSeeText('Upload a roster screenshot or trip PDF to instantly convert your schedule into calendar-ready events.')
            ->assertSeeInOrder([
                'Jeppesen Crew Access',
                'Schedule Extractor',
            ])
            ->assertSeeText('Extract Schedule');

        $this->get(route('parse.index'))
            ->assertOk()
            ->assertViewIs('dashboard')
            ->assertSee('wire:name="schedule-extractor"', false)
            ->assertSeeText('Jeppesen Crew Access')
            ->assertSeeText('Schedule Extractor')
            ->assertSeeText('Extract Schedule');
    }

    public function test_parse_pasted_text_stores_parse_key_in_session_and_result_in_cache()
    {
        $text = "Trip Information\nDate: 13Jun2026\nTrip ID: 13131\nCrew on trip - (5)\nCP 4620 Michael Blackburn";

        $this->mock(ScheduleFormatParser::class, function (MockInterface $mock) use ($text): void {
            $mock->shouldReceive('parse')
                ->once()
                ->with($text, null)
                ->andReturn([
                    'trip' => ['trip_number' => '13131'],
                    'calendar_events' => [$this->calendarEvent()],
                ]);
        });

        $user = User::factory()->make();

        Livewire::actingAs($user)
            ->test(ScheduleExtractor::class)
            ->set('text', $text)
            ->call('parseRoster')
            ->assertHasNoErrors()
            ->assertSet('view', 'results');

        $this->assertTrue(session()->has('latest_parse_key'));

        $parseKey = session('latest_parse_key');
        $this->assertIsString($parseKey);

        $parsed = Cache::get($this->cacheKeyForSession($parseKey));
        $this->assertIsArray($parsed);
        $this->assertEquals('roster', $parsed['type']);
        $this->assertEquals('text', $parsed['source']);
        $this->assertArrayNotHasKey('raw', $parsed);
        $this->assertArrayNotHasKey('raw_text', $parsed);

        $parseRequest = ParseRequest::query()->latest('id')->firstOrFail();
        $this->assertSame('success', $parseRequest->status);
        $this->assertSame('pasted_text', $parseRequest->source_type);
        $this->assertNull($parseRequest->file_hash);

        $page = $this->get(route('parse.index'));
        $page->assertOk()
            ->assertSee('Extracted Schedule')
            ->assertSee('rounded-lg border border-[#1B365D]/15 bg-white shadow-sm', false)
            ->assertSee('border-b border-[#1B365D]/10 bg-[#F8FAFD] px-5 py-4', false)
            ->assertDontSee('rounded-[1.9rem]', false);
    }

    public function test_non_flight_event_card_header_displays_date_without_times(): void
    {
        $source = [
            'source' => 'text',
            'document_type' => null,
            'file' => null,
            'mime' => null,
            'raw_text' => 'Duty event raw text',
            'meta' => [],
        ];

        $parsed = [
            'trip' => ['trip_number' => '13131'],
            'calendar_events' => [[
                'title' => 'Hotel Check-In',
                'type' => 'duty',
                'start' => '2026-06-13T14:00:00Z',
                'end' => '2026-06-13T16:00:00Z',
                'metadata' => [
                    'download_id' => 'event-123',
                ],
            ]],
        ];

        $this->mock(ScheduleInputResolver::class, function (MockInterface $mock) use ($source): void {
            $mock->shouldReceive('resolve')
                ->once()
                ->andReturn($source);
        });

        $this->mock(ScheduleFormatParser::class, function (MockInterface $mock) use ($parsed): void {
            $mock->shouldReceive('parse')
                ->once()
                ->with('Duty event raw text', null)
                ->andReturn($parsed);
        });

        Livewire::actingAs(User::factory()->make())
            ->test(ScheduleExtractor::class)
            ->set('text', 'ignored because resolver is mocked')
            ->call('parseRoster')
            ->assertHasNoErrors();

        $page = $this->get(route('parse.index'));

        $page->assertOk()
            ->assertSee('Jun 13', false)
            ->assertSee('Jun 13 • 1400 Z - 1600 Z', false);
    }

    public function test_parse_failure_is_recorded_and_logged_without_input_contents(): void
    {
        Log::spy();

        $this->mock(ScheduleInputResolver::class, function (MockInterface $mock): void {
            $mock->shouldReceive('resolve')
                ->once()
                ->andThrow(new RuntimeException('Parser unavailable'));
        });

        Livewire::actingAs(User::factory()->make())
            ->test(ScheduleExtractor::class)
            ->set('text', 'private roster contents')
            ->call('parseRoster')
            ->assertHasErrors(['file']);

        $parseRequest = ParseRequest::query()->latest('id')->firstOrFail();
        $this->assertSame('failed', $parseRequest->status);
        $this->assertSame(RuntimeException::class, $parseRequest->error_code);
        $this->assertArrayNotHasKey('raw', $parseRequest->getAttributes());
        $this->assertArrayNotHasKey('raw_text', $parseRequest->getAttributes());

        Log::shouldHaveReceived('error')->once()->with('K4 parse failed', [
            'parse_request_id' => $parseRequest->id,
            'error' => 'Parser unavailable',
        ]);
    }

    public function test_parse_roster_routes_published_roster_uploads_to_document_parser(): void
    {
        $source = [
            'source' => 'pdf',
            'document_type' => ScheduleDocumentType::PublishedRoster->value,
            'file' => 'uploads/published-roster.pdf',
            'mime' => 'application/pdf',
            'raw' => 'Published Roster raw text',
            'raw_text' => 'Published Roster raw text',
            'meta' => ['date' => '17Jun2026'],
        ];

        $parsed = [
            'trip' => ['trip_number' => '13131'],
            'calendar_events' => [$this->calendarEvent()],
        ];

        $this->mock(ScheduleInputResolver::class, function (MockInterface $mock) use ($source): void {
            $mock->shouldReceive('resolve')
                ->once()
                ->andReturn($source);
        });

        $this->mock(ScheduleFormatParser::class, function (MockInterface $mock) use ($parsed, $source): void {
            $mock->shouldReceive('parse')
                ->once()
                ->with($source['raw_text'], ScheduleDocumentType::PublishedRoster->value)
                ->andReturn($parsed);
        });

        Livewire::actingAs(User::factory()->make())
            ->test(ScheduleExtractor::class)
            ->set('text', 'ignored because resolver is mocked')
            ->call('parseRoster')
            ->assertHasNoErrors();

        $parseKey = session('latest_parse_key');
        $this->assertIsString($parseKey);

        $result = Cache::get($this->cacheKeyForSession($parseKey));
        $this->assertIsArray($result);
        $this->assertSame('pdf', $result['source']);
        $this->assertSame(ScheduleDocumentType::PublishedRoster->value, $result['document_type']);
        $this->assertSame('17Jun2026', $result['meta']['date']);
    }

    public function test_parse_roster_routes_trip_information_uploads_to_document_parser(): void
    {
        $source = [
            'source' => 'pdf',
            'document_type' => ScheduleDocumentType::TripInformation->value,
            'file' => 'uploads/trip-information.pdf',
            'mime' => 'application/pdf',
            'raw' => 'Trip Information raw text',
            'raw_text' => 'Trip Information raw text',
            'meta' => ['trip_id' => '13131'],
        ];

        $parsed = [
            'trip' => ['trip_number' => '13131'],
            'calendar_events' => [$this->calendarEvent()],
        ];

        $this->mock(ScheduleInputResolver::class, function (MockInterface $mock) use ($source): void {
            $mock->shouldReceive('resolve')
                ->once()
                ->andReturn($source);
        });

        $this->mock(ScheduleFormatParser::class, function (MockInterface $mock) use ($parsed, $source): void {
            $mock->shouldReceive('parse')
                ->once()
                ->with($source['raw_text'], ScheduleDocumentType::TripInformation->value)
                ->andReturn($parsed);
        });

        Livewire::actingAs(User::factory()->make())
            ->test(ScheduleExtractor::class)
            ->set('text', 'ignored because resolver is mocked')
            ->call('parseRoster')
            ->assertHasNoErrors();

        $parseKey = session('latest_parse_key');
        $this->assertIsString($parseKey);

        $result = Cache::get($this->cacheKeyForSession($parseKey));
        $this->assertIsArray($result);
        $this->assertSame('pdf', $result['source']);
        $this->assertSame(ScheduleDocumentType::TripInformation->value, $result['document_type']);
        $this->assertSame('13131', $result['meta']['trip_id']);
    }

    private function cacheKeyForSession(string $parseKey): string
    {
        return 'sessions:'.$this->sessionCacheNamespace().":parsed_results:{$parseKey}";
    }

    /** @return array<string, mixed> */
    private function calendarEvent(): array
    {
        return [
            'title' => 'Duty',
            'type' => 'duty',
            'start' => '2026-06-13T14:00:00+00:00',
            'end' => '2026-06-13T16:00:00+00:00',
            'metadata' => [],
        ];
    }

    private function sessionCacheNamespace(): string
    {
        return (string) session('parsed_results_namespace');
    }
}
