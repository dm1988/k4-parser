<?php

namespace Tests\Feature\Livewire;

use App\DTOs\ParserResultData;
use App\Exceptions\ParseSourceResolutionException;
use App\Livewire\ScheduleExtractor;
use App\Models\User;
use App\Services\JcaScheduleParsingService;
use App\Services\ParserResultCache;
use App\Services\ScheduleFormatParser;
use App\Services\ScheduleInputResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use LogicException;
use Mockery\MockInterface;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class ScheduleExtractorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_starts_on_the_upload_view_without_a_cached_result(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(ScheduleExtractor::class)
            ->assertSet('view', 'upload')
            ->assertSet('parseKey', null)
            ->assertSee('Schedule Extractor')
            ->assertSee('Ready to extract')
            ->assertDontSee('Extracted Schedule');
    }

    public function test_the_view_state_cannot_be_changed_by_the_client(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);
        $this->expectExceptionMessage('Cannot update locked property: [view]');

        Livewire::actingAs(User::factory()->create())
            ->test(ScheduleExtractor::class)
            ->set('view', 'tampered');
    }

    public function test_it_restores_the_latest_cached_result_on_mount(): void
    {
        $result = $this->cacheResult('01JCACHEDPARSEKEYABC12', 'Cached duty');

        Livewire::actingAs(User::factory()->create())
            ->test(ScheduleExtractor::class)
            ->assertSet('view', 'results')
            ->assertSet('parseKey', $result->parseKey)
            ->assertSee('Extracted Schedule')
            ->assertSee('Cached duty')
            ->assertSee('Extract another roster');
    }

    public function test_it_falls_back_to_the_latest_result_when_the_component_parse_key_is_stale(): void
    {
        $staleResult = $this->cacheResult('01JSTALEPARSEKEYABC123', 'Stale duty');
        $component = Livewire::actingAs(User::factory()->create())
            ->test(ScheduleExtractor::class)
            ->assertSet('parseKey', $staleResult->parseKey)
            ->assertSee('Stale duty');

        $namespace = session('parsed_results_namespace');
        $this->assertIsString($namespace);

        Cache::forget("sessions:{$namespace}:parsed_results:{$staleResult->parseKey}");
        Cache::forget("parsed_results:{$staleResult->parseKey}");

        $latestResult = $this->cacheResult('01JLATESTPARSEKEYABC12', 'Latest duty');

        $component
            ->refresh()
            ->assertSet('parseKey', $staleResult->parseKey)
            ->assertSee('Latest duty')
            ->assertDontSee('Stale duty');

        $this->assertSame($latestResult->parseKey, app(ParserResultCache::class)->latest()?->parseKey);
    }

    public function test_it_uses_the_shared_roster_validation_rules_and_messages(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ScheduleExtractor::class)
            ->call('parseRoster')
            ->assertHasErrors([
                'file' => 'required_without',
                'text' => 'required_without',
            ])
            ->assertSee('Please provide either roster text or an uploaded file.')
            ->set('text', 'Roster text')
            ->set('eventTypes', ['not-a-real-type'])
            ->call('parseRoster')
            ->assertHasErrors(['eventTypes.0' => 'in'])
            ->assertSee('The selected event type is invalid.');
    }

    public function test_it_rejects_an_unsupported_upload(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(ScheduleExtractor::class)
            ->set('file', UploadedFile::fake()->create('roster.csv', 10, 'text/csv'))
            ->call('parseRoster')
            ->assertHasErrors(['file' => 'mimes'])
            ->assertSet('view', 'upload');
    }

    public function test_source_validation_errors_clear_when_the_corresponding_input_changes(): void
    {
        $component = Livewire::actingAs(User::factory()->create())
            ->test(ScheduleExtractor::class)
            ->call('parseRoster')
            ->assertHasErrors(['file', 'text'])
            ->set('text', 'Roster text')
            ->assertHasNoErrors('text')
            ->assertHasErrors('file');

        $component
            ->set('file', UploadedFile::fake()->create('roster.pdf', 120, 'application/pdf'))
            ->assertHasNoErrors('file');
    }

    public function test_source_type_resolution_rejects_a_validated_upload_with_an_unknown_mime_type(): void
    {
        $file = new class(__FILE__, 'roster.pdf', 'application/pdf', null, true) extends UploadedFile
        {
            public function getMimeType(): ?string
            {
                return null;
            }
        };
        $component = Livewire::actingAs(User::factory()->create())
            ->test(ScheduleExtractor::class)
            ->instance();
        $method = new ReflectionMethod($component, 'resolveSourceType');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Validated upload has an unsupported MIME type.');

        $method->invoke($component, $file);
    }

    public function test_it_parses_pasted_roster_text_without_a_redirect(): void
    {
        $this->mockResolvedSource(null, 'Roster text');
        $this->mockParsedEvents([$this->event('Pasted text duty')]);

        Livewire::actingAs(User::factory()->create())
            ->test(ScheduleExtractor::class)
            ->set('text', 'Roster text')
            ->set('eventTypes', [2 => 'duty', 7 => 'flight'])
            ->call('parseRoster')
            ->assertHasNoErrors()
            ->assertNoRedirect()
            ->assertSet('view', 'results')
            ->assertSet('eventTypes', ['duty', 'flight'])
            ->assertSee('Pasted text duty')
            ->assertSee('Download all (.ics)');

        $latest = app(ParserResultCache::class)->latest();
        $this->assertNotNull($latest);
        $this->assertSame(['duty', 'flight'], $latest->filters);
    }

    public function test_it_parses_a_pdf_temporary_upload_with_a_local_real_path(): void
    {
        $this->mockResolvedSource('application/pdf', 'PDF extracted text');
        $this->mockParsedEvents([$this->event('PDF duty')]);

        Livewire::actingAs(User::factory()->create())
            ->test(ScheduleExtractor::class)
            ->set('file', UploadedFile::fake()->create('roster.pdf', 120, 'application/pdf'))
            ->call('parseRoster')
            ->assertHasNoErrors()
            ->assertNoRedirect()
            ->assertSet('view', 'results')
            ->assertSet('file', null)
            ->assertSee('PDF duty');
    }

    public function test_it_parses_an_image_temporary_upload(): void
    {
        $this->mockResolvedSource('image/png', 'Image OCR text');
        $this->mockParsedEvents([$this->event('Image duty')]);

        Livewire::actingAs(User::factory()->create())
            ->test(ScheduleExtractor::class)
            ->set('file', UploadedFile::fake()->image('roster.png', 300, 200))
            ->call('parseRoster')
            ->assertHasNoErrors()
            ->assertNoRedirect()
            ->assertSet('view', 'results')
            ->assertSee('Image duty');
    }

    public function test_source_resolution_failure_stays_on_upload_and_preserves_the_previous_result(): void
    {
        $previous = $this->cacheResult('01JPREVIOUSPARSEKEY1234', 'Previous duty');

        $this->mock(ScheduleInputResolver::class, function (MockInterface $mock): void {
            $mock->shouldReceive('resolve')
                ->once()
                ->andThrow(new RuntimeException('Parser unavailable'));
        });

        Livewire::actingAs(User::factory()->create())
            ->test(ScheduleExtractor::class)
            ->call('extractAnotherRoster')
            ->set('text', 'Roster text')
            ->call('parseRoster')
            ->assertSet('view', 'upload')
            ->assertSet('parseKey', $previous->parseKey)
            ->assertHasErrors(['file'])
            ->assertSee('Roster text resolution failed: Parser unavailable');

        $latest = app(ParserResultCache::class)->latest();
        $this->assertNotNull($latest);
        $this->assertSame($previous->parseKey, $latest->parseKey);
    }

    public function test_source_resolution_errors_support_multiple_messages_and_livewire_field_names(): void
    {
        $this->mock(JcaScheduleParsingService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('parseRoster')
                ->once()
                ->andThrow(new ParseSourceResolutionException('Source resolution failed.', [
                    'file' => [
                        'The PDF could not be read.',
                        'The PDF appears damaged.',
                    ],
                    'event_types.0' => ['The selected event type is unavailable.'],
                ]));
        });

        $component = Livewire::actingAs(User::factory()->create())
            ->test(ScheduleExtractor::class)
            ->set('text', 'Roster text')
            ->call('parseRoster')
            ->assertSet('view', 'upload')
            ->assertHasErrors(['file', 'eventTypes.0'])
            ->assertSee('The selected event type is unavailable.');

        $this->assertSame([
            'The PDF could not be read.',
            'The PDF appears damaged.',
        ], $component->instance()->getErrorBag()->get('file'));
    }

    public function test_extract_another_roster_resets_form_state_without_clearing_cache_or_filters(): void
    {
        $previous = $this->cacheResult('01JPREVIOUSPARSEKEY1234', 'Previous duty', ['flight']);

        Livewire::actingAs(User::factory()->create())
            ->test(ScheduleExtractor::class)
            ->call('parseRoster')
            ->assertHasErrors(['file', 'text'])
            ->set('text', 'Temporary text')
            ->call('extractAnotherRoster')
            ->assertSet('view', 'upload')
            ->assertSet('file', null)
            ->assertSet('text', '')
            ->assertSet('eventTypes', ['flight'])
            ->assertSet('parseKey', $previous->parseKey)
            ->assertHasNoErrors();

        $latest = app(ParserResultCache::class)->latest();
        $this->assertNotNull($latest);
        $this->assertSame($previous->parseKey, $latest->parseKey);
    }

    public function test_zero_event_parse_stays_on_upload_without_replacing_the_previous_latest_result(): void
    {
        $previous = $this->cacheResult('01JPREVIOUSPARSEKEY1234', 'Previous duty');
        $this->mockResolvedSource(null, 'Roster text');
        $this->mockParsedEvents([]);

        Livewire::actingAs(User::factory()->create())
            ->test(ScheduleExtractor::class)
            ->call('extractAnotherRoster')
            ->set('text', 'Roster text')
            ->call('parseRoster')
            ->assertSet('view', 'upload')
            ->assertSet('parseKey', $previous->parseKey)
            ->assertHasErrors(['file'])
            ->assertSee('No calendar events were found in that schedule.')
            ->assertDontSee('Extracted Schedule');

        $latest = app(ParserResultCache::class)->latest();
        $this->assertNotNull($latest);
        $this->assertSame($previous->parseKey, $latest->parseKey);
    }

    public function test_first_zero_event_parse_does_not_become_the_latest_result(): void
    {
        $this->mockResolvedSource(null, 'Roster text');
        $this->mockParsedEvents([]);

        Livewire::actingAs(User::factory()->create())
            ->test(ScheduleExtractor::class)
            ->set('text', 'Roster text')
            ->call('parseRoster')
            ->assertSet('view', 'upload')
            ->assertSet('parseKey', null)
            ->assertHasErrors(['file'])
            ->assertSee('No calendar events were found in that schedule.');

        $this->assertNull(app(ParserResultCache::class)->latest());
    }

    public function test_component_actions_enforce_authentication_verification_feature_and_gate_access(): void
    {
        Livewire::test(ScheduleExtractor::class)
            ->call('parseRoster')
            ->assertUnauthorized();

        Livewire::actingAs(User::factory()->unverified()->create())
            ->test(ScheduleExtractor::class)
            ->call('parseRoster')
            ->assertForbidden();

        Config::set('features.schedule_parser.enabled', false);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(ScheduleExtractor::class)
            ->call('parseRoster')
            ->assertForbidden();

        Config::set('features.schedule_parser', ['for_all_users' => true]);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(ScheduleExtractor::class)
            ->call('parseRoster')
            ->assertForbidden();

        Config::set('features.schedule_parser.enabled', true);
        Config::set('features.schedule_parser.for_all_users', false);

        Livewire::actingAs(User::factory()->create())
            ->test(ScheduleExtractor::class)
            ->call('parseRoster')
            ->assertForbidden();
    }

    private function mockResolvedSource(?string $mime, string $rawText): void
    {
        $this->mock(ScheduleInputResolver::class, function (MockInterface $mock) use ($mime, $rawText): void {
            $mock->shouldReceive('resolve')
                ->once()
                ->withArgs(function (?UploadedFile $file, ?string $text) use ($mime): bool {
                    if ($mime === null) {
                        return $file === null && $text === 'Roster text';
                    }

                    return $file instanceof TemporaryUploadedFile
                        && $file->getMimeType() === $mime
                        && is_file($file->getRealPath())
                        && $text === null;
                })
                ->andReturn([
                    'source' => $mime === null ? 'text' : ($mime === 'application/pdf' ? 'pdf' : 'image'),
                    'document_type' => null,
                    'file' => null,
                    'mime' => $mime,
                    'raw_text' => $rawText,
                    'meta' => null,
                ]);
        });
    }

    /** @param list<array<string, mixed>> $events */
    private function mockParsedEvents(array $events): void
    {
        $this->mock(ScheduleFormatParser::class, function (MockInterface $mock) use ($events): void {
            $mock->shouldReceive('parse')
                ->once()
                ->andReturn([
                    'trip' => ['trip_number' => '13131'],
                    'calendar_events' => $events,
                ]);
        });
    }

    /** @return array<string, mixed> */
    private function event(string $title): array
    {
        return [
            'title' => $title,
            'type' => 'duty',
            'start' => '2026-06-13T14:00:00+00:00',
            'end' => '2026-06-13T16:00:00+00:00',
            'metadata' => [],
        ];
    }

    /** @param list<string> $filters */
    private function cacheResult(string $parseKey, string $title, array $filters = []): ParserResultData
    {
        $result = ParserResultData::fromArray([
            'type' => 'roster',
            'source' => 'text',
            'parse_key' => $parseKey,
            'filters' => $filters,
            'parsed' => [
                'trip' => [],
                'calendar_events' => [[
                    ...$this->event($title),
                    'download_id' => '01JTESTEVENTKEYABC123',
                ]],
            ],
        ]);

        app(ParserResultCache::class)->put($result);

        return $result;
    }
}
