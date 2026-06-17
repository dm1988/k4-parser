<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\RosterDocumentParser;
use App\Services\RosterSourceResolver;
use Mockery\MockInterface;
use Tests\TestCase;

class ParseUploadTest extends TestCase
{
    public function test_parse_pasted_text_stores_parsed_result_in_session()
    {
        $text = "Trip Information\nDate: 13Jun2026\nTrip ID: 13131\nCrew on trip - (5)\nCP 4620 Michael Blackburn";

        $user = User::factory()->make();

        $response = $this->followingRedirects()->actingAs($user)->post('/parse/roster', [
            'text' => $text,
        ]);

        $response->assertStatus(200);

        $this->assertTrue(session()->has('parsed_result'));

        $parsed = session('parsed_result');
        $this->assertIsArray($parsed);
        $this->assertEquals('roster', $parsed['type']);
        $this->assertEquals('text', $parsed['source']);
        $this->assertStringContainsString('Trip Information', $parsed['raw']);
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

        $response
            ->assertRedirect()
            ->assertSessionHas('result', function (array $result): bool {
                return $result['source'] === 'pdf'
                    && $result['document_type'] === RosterSourceResolver::PDF_TYPE_PUBLISHED_ROSTER
                    && $result['meta']['date'] === '17Jun2026';
            });
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

        $response
            ->assertRedirect()
            ->assertSessionHas('result', function (array $result): bool {
                return $result['source'] === 'pdf'
                    && $result['document_type'] === RosterSourceResolver::PDF_TYPE_TRIP_INFORMATION
                    && $result['meta']['trip_id'] === '13131';
            });
    }
}
