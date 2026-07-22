<?php

namespace Tests\Feature;

use App\DTOs\ParserResultData;
use App\Models\User;
use App\Services\Infrastructure\EngineResultCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParserCacheIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_parse_in_another_tab_replaces_session_latest_but_keeps_older_parse_addressable(): void
    {
        $this->actingAs(User::factory()->create());

        $cache = app(EngineResultCache::class);
        $first = $this->parserResult('01JFIRSTPARSEKEYABC123', 'First tab event', '01JFIRSTEVENTKEYABC123');
        $second = $this->parserResult('01JSECONDPARSEKEYABC12', 'Second tab event', '01JSECONDEVENTKEYABC12');

        $cache->put($first);
        $firstNamespace = session('parsed_results_namespace');
        $cache->put($second);

        $latest = $cache->latest();
        $this->assertNotNull($latest);
        $this->assertSame($second->parseKey, $latest->parseKey);
        $this->assertSame($firstNamespace, session('parsed_results_namespace'));

        $older = $cache->get((string) $first->parseKey);
        $this->assertNotNull($older);
        $this->assertSame('First tab event', $older->parsed['calendar_events'][0]['title']);

        $this->get(route('parse.export.event', [
            'eventId' => '01JFIRSTEVENTKEYABC123',
            'parse_key' => $first->parseKey,
        ]))
            ->assertOk()
            ->assertSee('SUMMARY:First tab event');
    }

    public function test_disclosed_parse_key_is_not_accessible_by_another_authorized_user(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $result = $this->parserResult('01JSHAREDPARSEKEYABC12', 'Bearer key event', '01JSHAREDEVENTKEYABC12');

        $this->actingAs($firstUser);
        app(EngineResultCache::class)->put($result);

        session()->invalidate();
        session()->start();

        $this->actingAs($secondUser)
            ->get(route('parse.export.event', [
                'eventId' => '01JSHAREDEVENTKEYABC12',
                'parse_key' => $result->parseKey,
            ]))
            ->assertNotFound();
    }

    public function test_parse_key_remains_accessible_to_its_owner_after_the_session_changes(): void
    {
        $user = User::factory()->create();
        $result = $this->parserResult('01JOWNERPARSEKEYABC123', 'Owned event', '01JOWNEREVENTKEYABC123');

        $this->actingAs($user);
        app(EngineResultCache::class)->put($result);

        session()->invalidate();
        session()->start();

        $this->actingAs($user)
            ->get(route('parse.export.event', [
                'eventId' => '01JOWNEREVENTKEYABC123',
                'parse_key' => $result->parseKey,
            ]))
            ->assertOk()
            ->assertSee('SUMMARY:Owned event');
    }

    private function parserResult(string $parseKey, string $title, string $eventId): ParserResultData
    {
        return ParserResultData::fromArray([
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
                    'download_id' => $eventId,
                    'metadata' => [],
                ]],
            ],
        ]);

    }
}
