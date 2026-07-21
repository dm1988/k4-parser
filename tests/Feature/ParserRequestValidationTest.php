<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ParserRequestValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->make());
    }

    public function test_parse_roster_request_rejects_invalid_event_type_filters(): void
    {
        $response = $this->from(route('parse.index'))->post(route('parse.roster'), [
            'event_types' => ['not-a-real-type'],
        ]);

        $response->assertSessionHasErrors(['event_types.0' => 'The selected event type is invalid.']);
    }

    public function test_parse_roster_request_requires_text_or_a_supported_upload(): void
    {
        $missingSource = $this->from(route('parse.index'))->post(route('parse.roster'));

        $missingSource->assertSessionHasErrors([
            'file' => 'Please provide either roster text or an uploaded file.',
            'text' => 'Please provide either roster text or an uploaded file.',
        ]);

        $unsupportedUpload = $this->from(route('parse.index'))->post(route('parse.roster'), [
            'file' => UploadedFile::fake()->create('roster.csv', 10, 'text/csv'),
        ]);

        $unsupportedUpload->assertSessionHasErrors(['file']);
    }
}
