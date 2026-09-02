<?php

namespace Tests\Feature;

use App\Enums\ScheduleDocumentType;
use App\Livewire\ScheduleExtractor;
use App\Models\ExtractRequest;
use App\Models\User;
use App\Services\Schedule\Extractor\ScheduleFormatParser;
use App\Services\Schedule\ScheduleInputResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Mockery\Expectation;
use Mockery\ExpectationInterface;
use Mockery\MockInterface;
use Mockery\VerificationDirector;
use RuntimeException;
use Tests\TestCase;

class ExtractUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_parse_page_renders_centered_dropzone_and_disabled_extract_button(): void
    {
        $page = $this->actingAs(User::factory()->make())->get(route('parse.index'));
        $content = $page->getContent();

        $page->assertOk();
        $page->assertSee('wire:submit="extractRoster"', false);
        $page->assertSee('wire:model="files"', false);
        $page->assertSeeText('Drop your schedule here');
        $page->assertSeeText('Select up to five images, or one PDF. Click to browse your files.');
        $page->assertSee('wire:loading.attr="disabled"', false);
        $page->assertSee('data-extract-submit', false);
        $page->assertSee('dark:disabled:bg-slate-800', false);
        $page->assertSee('dark:disabled:text-slate-500', false);
        $page->assertSee('dark:border-slate-600 dark:bg-slate-800/80', false);
        $page->assertSee('disabled', false);
        $page->assertDontSee('class="cc-card overflow-hidden"', false);
        $page->assertDontSee('shadow-lg shadow-[#1B365D]/10', false);
        $page->assertSee('text-[#1B365D] dark:text-slate-100 md:text-5xl', false);
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
            $this->expectOnce($mock, 'parse')
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
            ->call('extractRoster')
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

        $extractRequest = ExtractRequest::query()->latest('id')->firstOrFail();
        $this->assertSame('success', $extractRequest->status);
        $this->assertSame('pasted_text', $extractRequest->source_type);
        $this->assertNull($extractRequest->file_hash);

        $page = $this->get(route('parse.index'));
        $page->assertOk()
            ->assertSee('Extracted Schedule')
            ->assertSee('rounded-lg border border-[#1B365D]/15 bg-white shadow-sm', false)
            ->assertSee('border-b border-[#1B365D]/10 bg-[#F8FAFD] px-4 py-4 dark:border-slate-700 dark:bg-slate-800 sm:px-6', false)
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
            $this->expectOnce($mock, 'resolve')
                ->andReturn($source);
        });

        $this->mock(ScheduleFormatParser::class, function (MockInterface $mock) use ($parsed): void {
            $this->expectOnce($mock, 'parse')
                ->with('Duty event raw text', null)
                ->andReturn($parsed);
        });

        Livewire::actingAs(User::factory()->make())
            ->test(ScheduleExtractor::class)
            ->set('text', 'ignored because resolver is mocked')
            ->call('extractRoster')
            ->assertHasNoErrors();

        $page = $this->get(route('parse.index'));

        $page->assertOk()
            ->assertSee('Jun 13', false)
            ->assertSee('Jun 13 • 1400 Z - 1600 Z', false);
    }

    public function test_parse_failure_is_recorded_and_logged_without_input_contents(): void
    {
        $log = Log::spy();

        $this->mock(ScheduleInputResolver::class, function (MockInterface $mock): void {
            $this->expectOnce($mock, 'resolve')
                ->andThrow(new RuntimeException('Parser unavailable'));
        });

        Livewire::actingAs(User::factory()->make())
            ->test(ScheduleExtractor::class)
            ->set('text', 'private roster contents')
            ->call('extractRoster')
            ->assertHasErrors(['files']);

        $extractRequest = ExtractRequest::query()->latest('id')->firstOrFail();
        $this->assertSame('failed', $extractRequest->status);
        $this->assertSame(RuntimeException::class, $extractRequest->error_code);
        $this->assertArrayNotHasKey('raw', $extractRequest->getAttributes());
        $this->assertArrayNotHasKey('raw_text', $extractRequest->getAttributes());

        $this->assertReceivedOnce($log, 'error')->with('K4 extraction failed', [
            'extract_request_id' => $extractRequest->id,
            'error_code' => RuntimeException::class,
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
            $this->expectOnce($mock, 'resolve')
                ->andReturn($source);
        });

        $this->mock(ScheduleFormatParser::class, function (MockInterface $mock) use ($parsed, $source): void {
            $this->expectOnce($mock, 'parse')
                ->with($source['raw_text'], ScheduleDocumentType::PublishedRoster->value)
                ->andReturn($parsed);
        });

        Livewire::actingAs(User::factory()->make())
            ->test(ScheduleExtractor::class)
            ->set('text', 'ignored because resolver is mocked')
            ->call('extractRoster')
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
            $this->expectOnce($mock, 'resolve')
                ->andReturn($source);
        });

        $this->mock(ScheduleFormatParser::class, function (MockInterface $mock) use ($parsed, $source): void {
            $this->expectOnce($mock, 'parse')
                ->with($source['raw_text'], ScheduleDocumentType::TripInformation->value)
                ->andReturn($parsed);
        });

        Livewire::actingAs(User::factory()->make())
            ->test(ScheduleExtractor::class)
            ->set('text', 'ignored because resolver is mocked')
            ->call('extractRoster')
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

    /** @phpstan-return Expectation */
    private function expectOnce(MockInterface $mock, string $method): ExpectationInterface
    {
        return $mock->shouldReceive($method)->once();
    }

    private function assertReceivedOnce(MockInterface $mock, string $method): VerificationDirector
    {
        return $mock->shouldHaveReceived($method)->once();
    }
}
