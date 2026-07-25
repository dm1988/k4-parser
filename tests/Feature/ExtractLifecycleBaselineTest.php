<?php

namespace Tests\Feature;

use App\DTOs\ExtractedResultData;
use App\Enums\ScheduleDocumentType;
use App\Livewire\ScheduleExtractor;
use App\Models\ExtractRequest;
use App\Models\User;
use App\Services\Infrastructure\EngineResultCache;
use App\Services\Schedule\Extractor\ScheduleFormatParser;
use App\Services\Schedule\ScheduleInputResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class ExtractLifecycleBaselineTest extends TestCase
{
    use RefreshDatabase;

    public function test_supported_pdf_upload_reaches_the_existing_parser_flow(): void
    {
        $file = UploadedFile::fake()->create('published-roster.pdf', 120, 'application/pdf');

        $this->mockResolvedSource(
            expectedMime: 'application/pdf',
            source: [
                'source' => 'pdf',
                'document_type' => ScheduleDocumentType::PublishedRoster->value,
                'file' => null,
                'mime' => 'application/pdf',
                'raw_text' => 'Published Roster extracted text',
                'meta' => ['page_count' => 2],
            ],
        );
        $this->mockParsedResult(ScheduleDocumentType::PublishedRoster->value, [$this->calendarEvent()]);

        Livewire::actingAs(User::factory()->create())
            ->test(ScheduleExtractor::class)
            ->set('file', $file)
            ->call('extractRoster')
            ->assertHasNoErrors()
            ->assertSet('view', 'results');

        $extractRequest = ExtractRequest::query()->latest('id')->firstOrFail();
        $this->assertSame('pdf', $extractRequest->source_type);
        $this->assertSame('roster_pdf', $extractRequest->parser_type);
        $this->assertSame('success', $extractRequest->status);
        $this->assertSame(2, $extractRequest->page_count);
        $this->assertNotNull($extractRequest->file_hash);
        $this->assertSame($file->getSize(), $extractRequest->file_size_bytes);
        $this->assertNotNull(app(EngineResultCache::class)->latest());
    }

    public function test_supported_image_upload_reaches_the_existing_parser_flow(): void
    {
        $file = UploadedFile::fake()->image('roster.png', 300, 200)->size(64);

        $this->mockResolvedSource(
            expectedMime: 'image/png',
            source: [
                'source' => 'image',
                'document_type' => null,
                'file' => null,
                'mime' => 'image/png',
                'raw_text' => 'Roster screenshot OCR text',
                'meta' => null,
            ],
        );
        $this->mockParsedResult(null, [$this->calendarEvent()]);

        Livewire::actingAs(User::factory()->create())
            ->test(ScheduleExtractor::class)
            ->set('file', $file)
            ->call('extractRoster')
            ->assertHasNoErrors()
            ->assertSet('view', 'results');

        $extractRequest = ExtractRequest::query()->latest('id')->firstOrFail();
        $this->assertSame('image', $extractRequest->source_type);
        $this->assertSame('screenshot', $extractRequest->parser_type);
        $this->assertSame('success', $extractRequest->status);
        $this->assertNotNull($extractRequest->file_hash);
        $this->assertSame($file->getSize(), $extractRequest->file_size_bytes);
        $this->assertNotNull(app(EngineResultCache::class)->latest());
    }

    public function test_source_resolution_failure_restores_input_and_preserves_the_latest_successful_result(): void
    {
        $user = User::factory()->create();
        $previousResult = $this->cacheResult('01JPREVIOUSPARSEKEY1234', 'Previous result');

        $this->mock(ScheduleInputResolver::class, function (MockInterface $mock): void {
            $mock->shouldReceive('resolve')
                ->once()
                ->andThrow(new RuntimeException('Parser unavailable'));
        });

        Livewire::actingAs($user)
            ->test(ScheduleExtractor::class)
            ->set('text', 'private roster contents')
            ->set('eventTypes', ['flight'])
            ->call('extractRoster')
            ->assertHasErrors(['file'])
            ->assertSee('Roster text resolution failed: Parser unavailable')
            ->assertSet('text', 'private roster contents')
            ->assertSet('eventTypes', ['flight'])
            ->assertSet('view', 'upload');

        $latest = app(EngineResultCache::class)->latest();
        $this->assertNotNull($latest);
        $this->assertSame($previousResult->parseKey, $latest->parseKey);
    }

    public function test_non_source_parser_exception_returns_an_error_and_preserves_the_latest_successful_result(): void
    {
        Log::spy();

        $user = User::factory()->create();
        $previousResult = $this->cacheResult('01JPREVIOUSPARSEKEY1234', 'Previous result');

        $this->mockResolvedSource(
            expectedMime: null,
            source: [
                'source' => 'text',
                'document_type' => null,
                'file' => null,
                'mime' => null,
                'raw_text' => 'Roster text',
                'meta' => null,
            ],
        );
        $this->mock(ScheduleFormatParser::class, function (MockInterface $mock): void {
            $mock->shouldReceive('parse')
                ->once()
                ->andThrow(new RuntimeException('Unexpected parser failure'));
        });

        try {
            Livewire::actingAs($user)
                ->test(ScheduleExtractor::class)
                ->set('text', 'Roster text')
                ->call('extractRoster');

            $this->fail('Expected the parser exception to be rethrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Unexpected parser failure', $exception->getMessage());
        }

        $latest = app(EngineResultCache::class)->latest();
        $this->assertNotNull($latest);
        $this->assertSame($previousResult->parseKey, $latest->parseKey);
        $this->assertSame('failed', ExtractRequest::query()->latest('id')->firstOrFail()->status);
    }

    public function test_successful_empty_parse_preserves_the_latest_successful_result(): void
    {
        $user = User::factory()->create();
        $previousResult = $this->cacheResult('01JPREVIOUSPARSEKEY1234', 'Previous result');

        $this->mockResolvedSource(
            expectedMime: null,
            source: [
                'source' => 'text',
                'document_type' => null,
                'file' => null,
                'mime' => null,
                'raw_text' => 'Roster text with no events',
                'meta' => null,
            ],
        );
        $this->mockParsedResult(null, []);

        Livewire::actingAs($user)
            ->test(ScheduleExtractor::class)
            ->set('text', 'Roster text with no events')
            ->call('extractRoster')
            ->assertHasErrors(['file'])
            ->assertSet('view', 'upload');

        $latest = app(EngineResultCache::class)->latest();
        $this->assertNotNull($latest);
        $this->assertSame($previousResult->parseKey, $latest->parseKey);

        $this->get(route('parse.index'))
            ->assertOk()
            ->assertSeeText('Previous result')
            ->assertDontSeeText('No calendar events matched the current filters.');
    }

    public function test_parser_exports_return_not_found_for_unknown_parse_and_event_keys(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('parse.export', ['parse_key' => '01JUNKNOWNPARSEKEY1234']))
            ->assertNotFound();
        $this->get(route('parse.export.event', [
            'eventId' => '01JUNKNOWNEVENTKEY1234',
            'parse_key' => '01JUNKNOWNPARSEKEY1234',
        ]))->assertNotFound();
        $this->get(route('parse.export.event.duty', [
            'eventId' => '01JUNKNOWNEVENTKEY1234',
            'parse_key' => '01JUNKNOWNPARSEKEY1234',
        ]))->assertNotFound();
    }

    public function test_parser_export_routes_require_authentication_and_verification(): void
    {
        $this->get(route('parse.export'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->unverified()->create())
            ->get(route('parse.export'))
            ->assertRedirect(route('verification.notice'));
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function mockResolvedSource(?string $expectedMime, array $source): void
    {
        $this->mock(ScheduleInputResolver::class, function (MockInterface $mock) use ($expectedMime, $source): void {
            $mock->shouldReceive('resolve')
                ->once()
                ->withArgs(function (?UploadedFile $file, ?string $text) use ($expectedMime): bool {
                    if ($expectedMime === null) {
                        return $file === null && is_string($text);
                    }

                    return $file instanceof UploadedFile && $file->getMimeType() === $expectedMime && $text === null;
                })
                ->andReturn($source);
        });
    }

    /** @param list<array<string, mixed>> $events */
    private function mockParsedResult(?string $documentType, array $events): void
    {
        $this->mock(ScheduleFormatParser::class, function (MockInterface $mock) use ($documentType, $events): void {
            $mock->shouldReceive('parse')
                ->once()
                ->withArgs(fn (string $text, ?string $type): bool => $text !== '' && $type === $documentType)
                ->andReturn([
                    'trip' => [],
                    'calendar_events' => $events,
                ]);
        });
    }

    private function cacheResult(string $parseKey, string $title): ExtractedResultData
    {
        $result = ExtractedResultData::fromArray([
            'type' => 'roster',
            'source' => 'text',
            'parse_key' => $parseKey,
            'parsed' => [
                'trip' => [],
                'calendar_events' => [[
                    'title' => $title,
                    'type' => 'duty',
                    'start' => '2026-06-13T14:00:00+00:00',
                    'end' => '2026-06-13T16:00:00+00:00',
                    'download_id' => '01JTESTEVENTKEYABC123',
                    'metadata' => [],
                ]],
            ],
        ]);

        app(EngineResultCache::class)->put($result);

        return $result;
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
}
