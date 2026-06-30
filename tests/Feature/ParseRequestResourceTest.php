<?php

namespace Tests\Feature;

use App\Filament\Resources\ParseRequests\Pages\CreateParseRequest;
use App\Filament\Resources\ParseRequests\Pages\ListParseRequests;
use App\Models\ParseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ParseRequestResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_can_search_parse_requests_in_the_resource_table(): void
    {
        $this->actingAs(User::factory()->create());

        $firstRequest = ParseRequest::create([
            'user_id' => User::factory()->create()->getKey(),
            'request_uuid' => '11111111-1111-1111-1111-111111111111',
            'source_type' => 'pasted_text',
            'parser_type' => 'roster',
            'status' => 'success',
            'parse_duration_ms' => 150,
            'file_hash' => str_repeat('1', 64),
            'file_size_bytes' => 1024,
            'page_count' => 1,
            'detected_event_count' => 4,
            'detected_flight_count' => 2,
            'detected_hotel_count' => 0,
            'app_version' => '1.0.0',
            'parser_version' => '2026.06',
        ]);

        $secondRequest = ParseRequest::create([
            'user_id' => User::factory()->create()->getKey(),
            'request_uuid' => '22222222-2222-2222-2222-222222222222',
            'source_type' => 'pdf',
            'parser_type' => 'published_roster',
            'status' => 'failed',
            'error_code' => 'RuntimeException',
            'parse_duration_ms' => 320,
            'file_hash' => str_repeat('2', 64),
            'file_size_bytes' => 4096,
            'page_count' => 8,
            'detected_event_count' => 0,
            'detected_flight_count' => 0,
            'detected_hotel_count' => 0,
            'app_version' => '1.0.1',
            'parser_version' => '2026.07',
        ]);

        Livewire::test(ListParseRequests::class)
            ->assertCanSeeTableRecords([$firstRequest, $secondRequest])
            ->searchTable('11111111-1111')
            ->assertCanSeeTableRecords([$firstRequest])
            ->assertCanNotSeeTableRecords([$secondRequest]);
    }

    public function test_authenticated_users_can_create_parse_requests_from_the_resource_form(): void
    {
        $this->actingAs(User::factory()->create());

        $owner = User::factory()->create();

        Livewire::test(CreateParseRequest::class)
            ->fillForm([
                'user_id' => $owner->getKey(),
                'request_uuid' => '6a8bbf93-a9be-4f4b-bf33-4f2237f2a0a1',
                'source_type' => 'pasted_text',
                'parser_type' => 'roster',
                'status' => 'success',
                'error_code' => null,
                'parse_duration_ms' => 124,
                'file_size_bytes' => 2048,
                'page_count' => 3,
                'detected_event_count' => 8,
                'detected_flight_count' => 5,
                'detected_hotel_count' => 1,
                'file_hash' => str_repeat('a', 64),
                'app_version' => '1.0.0',
                'parser_version' => '2026.06',
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('parse_requests', [
            'user_id' => $owner->getKey(),
            'request_uuid' => '6a8bbf93-a9be-4f4b-bf33-4f2237f2a0a1',
            'source_type' => 'pasted_text',
            'parser_type' => 'roster',
            'status' => 'success',
            'parse_duration_ms' => 124,
            'file_size_bytes' => 2048,
            'page_count' => 3,
            'detected_event_count' => 8,
            'detected_flight_count' => 5,
            'detected_hotel_count' => 1,
            'file_hash' => str_repeat('a', 64),
            'app_version' => '1.0.0',
            'parser_version' => '2026.06',
        ]);
    }
}
