<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;

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
}
