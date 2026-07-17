<?php

namespace Tests\Unit;

use App\DTOs\Flight;
use App\Services\ParserResultCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ParserResultCacheTest extends TestCase
{
    public function test_it_stores_normalized_results_in_both_cache_namespaces(): void
    {
        $service = app(ParserResultCache::class);
        $result = [
            'type' => 'flight',
            'source' => 'text',
            'document_type' => null,
            'file' => null,
            'mime' => null,
            'parsed' => [
                'trip' => [],
                'calendar_events' => [
                    Flight::fromArray([
                        'title' => 'CKS 240 ICN-HKG',
                        'type' => 'flight',
                        'download_url' => '',
                        'download_id' => '01JTESTEVENTKEYABC123',
                    ]),
                ],
            ],
            'filters' => [],
            'meta' => [],
            'parse_key' => '01JTESTPARSEKEYABC123',
        ];

        $service->put($result);

        $this->assertSame('01JTESTPARSEKEYABC123', session('latest_parse_key'));

        $cached = $service->get('01JTESTPARSEKEYABC123');

        $this->assertIsArray($cached);
        $this->assertIsArray($cached['parsed']['calendar_events'][0]);
        $this->assertSame('01JTESTEVENTKEYABC123', $cached['parsed']['calendar_events'][0]['download_id']);
        $this->assertIsString(session('parsed_results_namespace'));
        $this->assertNotNull(Cache::get('parsed_results:01JTESTPARSEKEYABC123'));
    }

    public function test_it_prefers_request_parse_key_before_session_lookup(): void
    {
        $service = app(ParserResultCache::class);

        session(['latest_parse_key' => '01JSESSIONPARSEKEYABC12']);
        Cache::put('parsed_results:01JREQUESTPARSEKEYABC12', ['parse_key' => 'request'], now()->addMinute());
        Cache::put('parsed_results:01JSESSIONPARSEKEYABC12', ['parse_key' => 'session'], now()->addMinute());

        $request = Request::create('/parse/export', 'GET', [
            'parse_key' => '01JREQUESTPARSEKEYABC12',
        ]);

        $this->assertSame(
            ['parse_key' => 'request'],
            $service->resolveForRequest($request),
        );
    }
}
