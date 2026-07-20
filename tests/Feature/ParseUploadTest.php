<?php

namespace Tests\Feature;

use App\Enums\ScheduleDocumentType;
use App\Models\ParseRequest;
use App\Models\User;
use App\Services\ScheduleFormatParser;
use App\Services\ScheduleInputResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class ParseUploadTest extends TestCase
{
    public function test_parse_page_renders_processing_status_panel_and_disabled_button_styles(): void
    {
        $page = $this->actingAs(User::factory()->make())->get(route('parse.index'));
        $content = $page->getContent();

        $page->assertOk();
        $page->assertSee('id="parserStatus"', false);
        $page->assertSee('class="mt-4 rounded-lg border border-[#1B365D]/10 px-4 py-3"', false);
        $page->assertSee('x-bind:class="statusPanelClasses"', false);
        $page->assertSee('x-bind:data-state="statusState"', false);
        $page->assertSee('data-parse-submit', false);
        $page->assertSee('disabled:bg-[#1B365D]/55', false);
        $page->assertSee('x-data="parserForm()"', false);
        $page->assertDontSee("const parserForm = document.getElementById('parserForm');", false);
        $page->assertSee('mx-auto grid max-w-6xl grid-cols-1 gap-6 px-5 py-6', false);
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
            ->assertViewHas('viewModel')
            ->assertSeeText('JCA Schedule Extractor')
            ->assertSeeText('Upload a roster screenshot or trip PDF. The JCA Extractor will instantly convert it into calendar-ready events.')
            ->assertSeeText('Extract Schedule');

        $this->get(route('parse.index'))
            ->assertOk()
            ->assertViewIs('dashboard')
            ->assertViewHas('viewModel')
            ->assertSeeText('JCA Schedule Extractor')
            ->assertSeeText('Extract Schedule');
    }

    public function test_parse_pasted_text_stores_parse_key_in_session_and_result_in_cache()
    {
        $text = "Trip Information\nDate: 13Jun2026\nTrip ID: 13131\nCrew on trip - (5)\nCP 4620 Michael Blackburn";

        $user = User::factory()->make();

        $response = $this->actingAs($user)->post('/parse/roster', [
            'text' => $text,
        ]);

        $response->assertRedirect();
        $response->assertSessionMissing('result');

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

        $response = $this->actingAs(User::factory()->make())->post(route('parse.roster'), [
            'text' => 'ignored because resolver is mocked',
        ]);

        $response->assertRedirect();

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

        $response = $this->actingAs(User::factory()->make())->post(route('parse.roster'), [
            'text' => 'private roster contents',
        ]);

        $response->assertRedirect();

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
            'calendar_events' => [],
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

        $response = $this->actingAs(User::factory()->make())->post(route('parse.roster'), [
            'text' => 'ignored because resolver is mocked',
        ]);

        $response->assertRedirect();
        $response->assertSessionMissing('result');

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
            'calendar_events' => [],
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

        $response = $this->actingAs(User::factory()->make())->post(route('parse.roster'), [
            'text' => 'ignored because resolver is mocked',
        ]);

        $response->assertRedirect();
        $response->assertSessionMissing('result');

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

    private function sessionCacheNamespace(): string
    {
        return (string) session('parsed_results_namespace');
    }
}
