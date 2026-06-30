<?php

namespace Tests\Feature;

use App\Models\ParseRequest;
use App\Models\User;
use App\Services\RosterDocumentParser;
use App\Services\RosterSourceResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class ParseUploadTest extends TestCase
{
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
        $page->assertOk()->assertSee('Parsed Output');
    }

    public function test_parse_failure_is_recorded_and_logged_without_input_contents(): void
    {
        Log::spy();

        $this->mock(RosterSourceResolver::class, function (MockInterface $mock): void {
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
            'document_type' => RosterSourceResolver::PDF_TYPE_PUBLISHED_ROSTER,
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

        $this->mock(RosterSourceResolver::class, function (MockInterface $mock) use ($source): void {
            $mock->shouldReceive('resolve')
                ->once()
                ->andReturn($source);
        });

        $this->mock(RosterDocumentParser::class, function (MockInterface $mock) use ($parsed, $source): void {
            $mock->shouldReceive('parse')
                ->once()
                ->with($source['raw_text'], RosterSourceResolver::PDF_TYPE_PUBLISHED_ROSTER)
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
        $this->assertSame(RosterSourceResolver::PDF_TYPE_PUBLISHED_ROSTER, $result['document_type']);
        $this->assertSame('17Jun2026', $result['meta']['date']);
    }

    public function test_parse_roster_routes_trip_information_uploads_to_document_parser(): void
    {
        $source = [
            'source' => 'pdf',
            'document_type' => RosterSourceResolver::PDF_TYPE_TRIP_INFORMATION,
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

        $this->mock(RosterSourceResolver::class, function (MockInterface $mock) use ($source): void {
            $mock->shouldReceive('resolve')
                ->once()
                ->andReturn($source);
        });

        $this->mock(RosterDocumentParser::class, function (MockInterface $mock) use ($parsed, $source): void {
            $mock->shouldReceive('parse')
                ->once()
                ->with($source['raw_text'], RosterSourceResolver::PDF_TYPE_TRIP_INFORMATION)
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
        $this->assertSame(RosterSourceResolver::PDF_TYPE_TRIP_INFORMATION, $result['document_type']);
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
