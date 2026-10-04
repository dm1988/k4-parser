<?php

namespace Tests\Feature;

use App\Filament\Resources\ExtractRequests\Pages\ListExtractRequests;
use App\Filament\Resources\ExtractRequests\Schemas\ExtractRequestForm;
use App\Models\ExtractRequest;
use App\Models\User;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExtractRequestResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploaded_file_counts_are_visible_and_sort_numerically(): void
    {
        $this->actingAs($this->makeAdminUser());
        $unknown = $this->createExtractRequest();
        $text = $this->createExtractRequest(['uploaded_file_count' => 0]);
        $single = $this->createExtractRequest(['uploaded_file_count' => 1]);
        $multiple = $this->createExtractRequest(['uploaded_file_count' => 3]);
        $larger = $this->createExtractRequest(['uploaded_file_count' => 12]);

        $this->assertNull($unknown->refresh()->uploaded_file_count);

        Livewire::test(ListExtractRequests::class)
            ->assertSee('Uploaded files')
            ->assertSee('Unknown')
            ->assertTableColumnExists('uploaded_file_count', fn (TextColumn $column): bool => $column->isSortable()
                && $column->getPlaceholder() === 'Unknown')
            ->assertTableColumnStateSet('uploaded_file_count', null, $unknown)
            ->assertTableColumnFormattedStateSet('uploaded_file_count', '0', $text)
            ->assertTableColumnFormattedStateSet('uploaded_file_count', '1', $single)
            ->assertTableColumnFormattedStateSet('uploaded_file_count', '3', $multiple)
            ->sortTable('uploaded_file_count', 'asc')
            ->assertCanSeeTableRecords([$text, $single, $multiple, $larger], inOrder: true)
            ->sortTable('uploaded_file_count', 'desc')
            ->assertCanSeeTableRecords([$larger, $multiple, $single, $text], inOrder: true);
    }

    #[DataProvider('uploadCountFormValues')]
    public function test_upload_count_form_rules_allow_only_nullable_non_negative_integers(mixed $value, bool $isValid): void
    {
        $this->actingAs($this->makeAdminUser());
        $component = Livewire::test(ListExtractRequests::class);
        $schema = ExtractRequestForm::configure(Schema::make($component->instance()));
        $field = collect($schema->getFlatFields())->first(
            fn (Field $field): bool => $field->getName() === 'uploaded_file_count',
        );
        $this->assertInstanceOf(TextInput::class, $field);

        $validator = Validator::make(['uploaded_file_count' => $value], [
            'uploaded_file_count' => $field->getValidationRules(),
        ]);

        $this->assertSame($isValid, $validator->passes());
    }

    /** @return array<string, array{mixed, bool}> */
    public static function uploadCountFormValues(): array
    {
        return [
            'unknown' => [null, true],
            'text' => [0, true],
            'one file' => [1, true],
            'multiple files' => [5, true],
            'no schedule limit on shared metrics' => [12, true],
            'negative' => [-1, false],
            'fraction' => [1.5, false],
        ];
    }

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

    public function test_table_configures_fixed_and_toggleable_columns_with_the_expected_default_visibility(): void
    {
        $this->actingAs($this->makeAdminUser());
        $component = Livewire::test(ListExtractRequests::class);

        foreach (['status', 'source_type', 'parser_type', 'created_at'] as $columnName) {
            $component->assertTableColumnExists(
                $columnName,
                fn (TextColumn $column): bool => ! $column->isToggleable(),
            );
        }

        foreach (['user.email', 'extraction_duration_ms', 'uploaded_file_count', 'detected_event_count', 'detected_flight_count'] as $columnName) {
            $component->assertTableColumnExists(
                $columnName,
                fn (TextColumn $column): bool => $column->isToggleable()
                    && ! $column->isToggledHiddenByDefault(),
            );
        }

        foreach (['request_uuid', 'detected_hotel_count', 'page_count', 'file_size_bytes', 'file_hash', 'app_version', 'extractor_version'] as $columnName) {
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
