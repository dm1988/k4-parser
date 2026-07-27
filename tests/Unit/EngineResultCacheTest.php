<?php

namespace Tests\Unit;

use App\DTOs\DutyEvent;
use App\DTOs\ExtractedResultData;
use App\Models\User;
use App\Services\Infrastructure\EngineResultCache;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class EngineResultCacheTest extends TestCase
{
    public function test_extracted_results_ttl_is_configured_as_an_integer(): void
    {
        $this->assertIsInt(config('cache.extracted_results_ttl'));
    }

    public function test_it_stores_normalized_results_in_both_cache_namespaces(): void
    {
        $service = app(EngineResultCache::class);
        $result = ExtractedResultData::fromArray([
            'type' => 'flight',
            'source' => 'text',
            'document_type' => null,
            'file' => null,
            'mime' => null,
            'parsed' => [
                'trip' => [],
                'calendar_events' => [
                    DutyEvent::fromArray([
                        'title' => 'Hotel Check-In',
                        'type' => 'duty',
                        'download_url' => '',
                        'download_id' => '01JTESTEVENTKEYABC123',
                    ]),
                ],
            ],
            'filters' => [],
            'meta' => [],
            'parse_key' => '01JTESTPARSEKEYABC123',
        ]);

        $service->put($result);

        $this->assertSame('01JTESTPARSEKEYABC123', session('latest_parse_key'));

        $cached = $service->get('01JTESTPARSEKEYABC123');

        $this->assertInstanceOf(ExtractedResultData::class, $cached);
        $this->assertIsArray($cached->parsed['calendar_events'][0]);
        $this->assertSame('01JTESTEVENTKEYABC123', $cached->parsed['calendar_events'][0]['download_id']);
        $this->assertSame('duty', $cached->parsed['calendar_events'][0]['type']);
        $this->assertIsString(session('parsed_results_namespace'));
        $this->assertNotNull(Cache::get('parsed_results:01JTESTPARSEKEYABC123'));
    }

    public function test_it_prefers_request_parse_key_before_session_lookup(): void
    {
        $service = app(EngineResultCache::class);
        $user = User::factory()->make(['id' => 123]);

        $this->actingAs($user);

        session(['latest_parse_key' => '01JSESSIONPARSEKEYABC12']);
        Cache::put('parsed_results:01JREQUESTPARSEKEYABC12', [
            'owner_id' => $user->id,
            'result' => ['parse_key' => 'request'],
        ], now()->addMinute());
        Cache::put('parsed_results:01JSESSIONPARSEKEYABC12', [
            'owner_id' => $user->id,
            'result' => ['parse_key' => 'session'],
        ], now()->addMinute());

        $request = Request::create('/parse/export', 'GET', [
            'parse_key' => '01JREQUESTPARSEKEYABC12',
        ]);

        $result = $service->resolveForRequest($request);

        $this->assertInstanceOf(ExtractedResultData::class, $result);
        $this->assertSame('request', $result->parseKey);
    }

    public function test_it_reads_the_same_session_result_from_the_cache_once_per_request(): void
    {
        $store = new class extends ArrayStore
        {
            public int $readCount = 0;

            public function get($key): mixed
            {
                $this->readCount++;

                return parent::get($key);
            }
        };

        config([
            'cache.default' => 'counting',
            'cache.stores.counting' => ['driver' => 'counting'],
        ]);
        Cache::extend('counting', fn (): Repository => new Repository($store));
        session(['parsed_results_namespace' => '01JTESTSESSIONKEYABC123']);

        Cache::put(
            'sessions:01JTESTSESSIONKEYABC123:parsed_results:01JTESTPARSEKEYABC123',
            ['parse_key' => '01JTESTPARSEKEYABC123'],
        );

        $service = app(EngineResultCache::class);
        $firstResult = $service->get('01JTESTPARSEKEYABC123');
        $secondResult = $service->get('01JTESTPARSEKEYABC123');

        $this->assertInstanceOf(ExtractedResultData::class, $firstResult);
        $this->assertInstanceOf(ExtractedResultData::class, $secondResult);
        $this->assertSame(1, $store->readCount);
    }

    public function test_put_invalidates_a_memoized_missing_result(): void
    {
        $service = app(EngineResultCache::class);

        $this->assertNull($service->get('01JTESTPARSEKEYABC123'));

        $service->put(ExtractedResultData::fromArray([
            'parse_key' => '01JTESTPARSEKEYABC123',
        ]));

        $this->assertSame('01JTESTPARSEKEYABC123', $service->get('01JTESTPARSEKEYABC123')?->parseKey);
    }

    public function test_it_rejects_ownerless_legacy_global_cache_entries(): void
    {
        $this->actingAs(User::factory()->make(['id' => 123]));
        Cache::put('parsed_results:01JLEGACYPARSEKEYABC123', [
            'parse_key' => '01JLEGACYPARSEKEYABC123',
        ], now()->addMinute());

        $this->assertNull(app(EngineResultCache::class)->get('01JLEGACYPARSEKEYABC123'));
    }
}
