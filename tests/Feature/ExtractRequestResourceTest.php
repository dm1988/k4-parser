<?php

namespace Tests\Feature;

use App\Filament\Resources\ExtractRequests\Pages\ListExtractRequests;
use App\Models\ExtractRequest;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExtractRequestResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_can_search_extract_requests_in_the_resource_table(): void
    {
        $this->actingAs($this->makeAdminUser());

        $firstRequest = ExtractRequest::create([
            'user_id' => User::factory()->create()->getKey(),
            'request_uuid' => '11111111-1111-1111-1111-111111111111',
            'source_type' => 'pasted_text',
            'parser_type' => 'roster',
            'status' => 'success',
            'extraction_duration_ms' => 150,
            'file_hash' => str_repeat('1', 64),
            'file_size_bytes' => 1024,
            'page_count' => 1,
            'detected_event_count' => 4,
            'detected_flight_count' => 2,
            'detected_hotel_count' => 0,
            'app_version' => '1.0.0',
            'extractor_version' => '2026.06',
        ]);

        $secondRequest = ExtractRequest::create([
            'user_id' => User::factory()->create()->getKey(),
            'request_uuid' => '22222222-2222-2222-2222-222222222222',
            'source_type' => 'pdf',
            'parser_type' => 'flight_plan',
            'status' => 'failed',
            'error_code' => 'RuntimeException',
            'extraction_duration_ms' => 320,
            'file_hash' => str_repeat('2', 64),
            'file_size_bytes' => 4096,
            'page_count' => 8,
            'detected_event_count' => 0,
            'detected_flight_count' => 0,
            'detected_hotel_count' => 0,
            'app_version' => '1.0.1',
            'extractor_version' => '2026.07',
        ]);

        Livewire::test(ListExtractRequests::class)
            ->assertCanSeeTableRecords([$firstRequest, $secondRequest])
            ->searchTable('11111111-1111')
            ->assertCanSeeTableRecords([$firstRequest])
            ->assertCanNotSeeTableRecords([$secondRequest]);
    }

    public function test_non_admin_users_can_not_access_extract_requests_table(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/extract-requests')->assertForbidden();
    }

    public function test_admins_can_filter_extract_requests_by_status_source_parser_user_and_error_state(): void
    {
        $this->actingAs($this->makeAdminUser());
        $user = User::factory()->create();
        $matchingRequest = $this->createExtractRequest([
            'user_id' => $user->getKey(),
            'status' => 'failed',
            'source_type' => 'pdf',
            'parser_type' => 'flight_plan',
            'error_code' => 'RuntimeException',
        ]);
        $otherRequest = $this->createExtractRequest();

        Livewire::test(ListExtractRequests::class)
            ->filterTable('status', 'failed')
            ->filterTable('source_type', 'pdf')
            ->filterTable('parser_type', 'flight_plan')
            ->filterTable('user', $user)
            ->filterTable('error_code', true)
            ->assertCanSeeTableRecords([$matchingRequest])
            ->assertCanNotSeeTableRecords([$otherRequest]);
    }

    public function test_source_filter_includes_and_filters_image_requests(): void
    {
        $this->actingAs($this->makeAdminUser());
        $imageRequest = $this->createExtractRequest([
            'source_type' => 'image',
            'parser_type' => 'screenshot',
        ]);
        $pdfRequest = $this->createExtractRequest([
            'source_type' => 'pdf',
        ]);

        Livewire::test(ListExtractRequests::class)
            ->assertTableFilterExists(
                'source_type',
                fn (SelectFilter $filter): bool => $filter->getOptions() === [
                    'pasted_text' => 'Pasted Text',
                    'pdf' => 'PDF',
                    'image' => 'Image',
                ],
            )
            ->filterTable('source_type', 'image')
            ->assertCanSeeTableRecords([$imageRequest])
            ->assertCanNotSeeTableRecords([$pdfRequest]);
    }

    public function test_table_keeps_identity_columns_visible_and_allows_secondary_columns_to_be_toggled(): void
    {
        $this->actingAs($this->makeAdminUser());
        $component = Livewire::test(ListExtractRequests::class);

        foreach (['request_uuid', 'status', 'source_type', 'parser_type', 'created_at'] as $columnName) {
            $component->assertTableColumnExists(
                $columnName,
                fn (TextColumn $column): bool => ! $column->isToggleable(),
            );
        }

        foreach (['user.email', 'extraction_duration_ms', 'detected_event_count', 'detected_flight_count', 'detected_hotel_count', 'error_code'] as $columnName) {
            $component->assertTableColumnExists(
                $columnName,
                fn (TextColumn $column): bool => $column->isToggleable()
                    && ! $column->isToggledHiddenByDefault(),
            );
        }

        foreach (['page_count', 'file_size_bytes', 'file_hash', 'app_version', 'extractor_version'] as $columnName) {
            $component->assertTableColumnExists(
                $columnName,
                fn (TextColumn $column): bool => $column->isToggleable()
                    && $column->isToggledHiddenByDefault(),
            );
        }
    }

    public function test_extract_requests_are_sorted_by_creation_time_descending_by_default(): void
    {
        $this->actingAs($this->makeAdminUser());
        $olderRequest = $this->createExtractRequest();
        $olderRequest->setCreatedAt('2026-07-01 12:00:00')->save();
        $newerRequest = $this->createExtractRequest();
        $newerRequest->setCreatedAt('2026-07-02 12:00:00')->save();

        Livewire::test(ListExtractRequests::class)
            ->assertCanSeeTableRecords([$newerRequest, $olderRequest], inOrder: true);
    }

    public function test_extract_requests_have_no_individual_delete_action_but_can_be_deleted_in_bulk(): void
    {
        $this->actingAs($this->makeAdminUser());
        $bulkRequests = collect([
            $this->createExtractRequest(),
            $this->createExtractRequest(),
        ]);

        Livewire::test(ListExtractRequests::class)
            ->assertTableActionDoesNotExist('delete')
            ->callTableBulkAction('delete', $bulkRequests);

        $bulkRequests->each(fn (ExtractRequest $extractRequest) => $this->assertModelMissing($extractRequest));
    }

    public function test_create_and_edit_pages_are_forbidden_by_the_extract_request_policy(): void
    {
        $this->actingAs($this->makeAdminUser());
        $extractRequest = $this->createExtractRequest();

        $this->get('/admin/extract-requests/create')->assertForbidden();
        $this->get("/admin/extract-requests/{$extractRequest->getKey()}/edit")->assertForbidden();

        Livewire::test(ListExtractRequests::class)
            ->assertTableActionHidden('edit', $extractRequest);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createExtractRequest(array $attributes = []): ExtractRequest
    {
        return ExtractRequest::query()->create(array_merge([
            'user_id' => User::factory()->create()->getKey(),
            'request_uuid' => fake()->uuid(),
            'source_type' => 'pasted_text',
            'parser_type' => 'roster',
            'status' => 'success',
            'extraction_duration_ms' => 150,
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
