<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class ParserRequestValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->make());
    }

    public function test_parse_flight_request_returns_a_clear_text_validation_message(): void
    {
        $response = $this->from(route('parse.index'))->post(route('parse.flight'), []);

        $response->assertSessionHasErrors(['text' => 'Please provide some text to parse.']);
    }

    public function test_parse_hotel_request_returns_a_clear_text_validation_message(): void
    {
        $response = $this->from(route('parse.index'))->post(route('parse.hotel'), []);

        $response->assertSessionHasErrors(['text' => 'Please provide some text to parse.']);
    }

    public function test_parse_roster_request_rejects_invalid_event_type_filters(): void
    {
        $response = $this->from(route('parse.index'))->post(route('parse.roster'), [
            'event_types' => ['not-a-real-type'],
        ]);

        $response->assertSessionHasErrors(['event_types.0' => 'The selected event type is invalid.']);
    }
}
