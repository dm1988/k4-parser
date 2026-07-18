<?php

namespace Tests\Feature;

use App\Filament\Resources\ParseRequests\Pages\ListParseRequests;
use App\Models\ParseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ParseRequestResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_can_search_parse_requests_in_the_resource_table(): void
    {
        $this->actingAs($this->makeAdminUser());

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

    public function test_non_admin_users_can_not_access_parse_requests_table(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/parse-requests')->assertForbidden();
    }

    public function test_admins_can_filter_parse_requests_by_status_source_parser_user_and_error_state(): void
    {
        $this->actingAs($this->makeAdminUser());
        $user = User::factory()->create();
        $matchingRequest = $this->createParseRequest([
            'user_id' => $user->getKey(),
            'status' => 'failed',
            'source_type' => 'pdf',
            'parser_type' => 'published_roster',
            'error_code' => 'RuntimeException',
        ]);
        $otherRequest = $this->createParseRequest();

        Livewire::test(ListParseRequests::class)
            ->filterTable('status', 'failed')
            ->filterTable('source_type', 'pdf')
            ->filterTable('parser_type', 'published_roster')
            ->filterTable('user', $user)
            ->filterTable('error_code', true)
            ->assertCanSeeTableRecords([$matchingRequest])
            ->assertCanNotSeeTableRecords([$otherRequest]);
    }

    public function test_parse_requests_are_sorted_by_creation_time_descending_by_default(): void
    {
        $this->actingAs($this->makeAdminUser());
        $olderRequest = $this->createParseRequest();
        $olderRequest->setCreatedAt('2026-07-01 12:00:00')->save();
        $newerRequest = $this->createParseRequest();
        $newerRequest->setCreatedAt('2026-07-02 12:00:00')->save();

        Livewire::test(ListParseRequests::class)
            ->assertCanSeeTableRecords([$newerRequest, $olderRequest], inOrder: true);
    }

    public function test_parse_requests_have_no_individual_delete_action_but_can_be_deleted_in_bulk(): void
    {
        $this->actingAs($this->makeAdminUser());
        $bulkRequests = collect([
            $this->createParseRequest(),
            $this->createParseRequest(),
        ]);

        Livewire::test(ListParseRequests::class)
            ->assertTableActionDoesNotExist('delete')
            ->callTableBulkAction('delete', $bulkRequests);

        $bulkRequests->each(fn (ParseRequest $parseRequest) => $this->assertModelMissing($parseRequest));
    }

    public function test_create_and_edit_pages_are_forbidden_by_the_parse_request_policy(): void
    {
        $this->actingAs($this->makeAdminUser());
        $parseRequest = $this->createParseRequest();

        $this->get('/admin/parse-requests/create')->assertForbidden();
        $this->get("/admin/parse-requests/{$parseRequest->getKey()}/edit")->assertForbidden();

        Livewire::test(ListParseRequests::class)
            ->assertTableActionHidden('edit', $parseRequest);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createParseRequest(array $attributes = []): ParseRequest
    {
        return ParseRequest::query()->create(array_merge([
            'user_id' => User::factory()->create()->getKey(),
            'request_uuid' => fake()->uuid(),
            'source_type' => 'pasted_text',
            'parser_type' => 'roster',
            'status' => 'success',
            'parse_duration_ms' => 150,
            'detected_event_count' => 4,
            'detected_flight_count' => 2,
            'detected_hotel_count' => 0,
        ], $attributes));
    }

    private function makeAdminUser(): User
    {
        $user = User::factory()->create();

        $user->forceFill([
            'role' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();

        return $user->refresh();
    }
}
