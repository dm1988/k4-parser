<?php

namespace Tests\Feature;

use App\Actions\CrewBids\DeleteBidPackage;
use App\Filament\Resources\BidPackages\BidPackageResource;
use App\Filament\Resources\BidPackages\Pages\ListBidPackages;
use App\Filament\Resources\BidPackages\Pages\ViewBidPackage;
use App\Livewire\CrewBids;
use App\Models\BidPackage;
use App\Models\BidWorkspace;
use App\Models\User;
use App\Services\CrewBids\ParseBidDocuments;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;
use Smalot\PdfParser\Document;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

class BidPackageResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_imports_the_private_december_777_package_with_all_three_sources(): void
    {
        $files = [];
        foreach (['Overview.xlsx', 'Lines.pdf', 'Trips.pdf'] as $source) {
            $name = '777 December 2026 '.$source;
            $path = storage_path('app/private/Bid_Packages/'.$name);
            if (! is_file($path)) {
                $this->markTestSkipped('The private December B777 package is unavailable.');
            }
            $files[] = UploadedFile::fake()->createWithContent($name, file_get_contents($path));
        }
        Storage::fake('local');
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ListBidPackages::class)->callAction('import', data: ['files' => $files])
            ->assertHasNoActionErrors();

        $package = BidPackage::query()->sole();
        $this->assertSame('2026-12', $package->period);
        $this->assertSame('777', $package->fleet);
        $this->assertNull($package->published_at);
        $this->assertSame(['overview' => true, 'lines' => true, 'trips' => true], $package->payload['coverage']);
        $this->assertArrayHasKey('F1', $package->payload['lines']);
        $this->assertNotEmpty($package->payload['trips']);
        $this->assertSame([], Storage::disk('local')->allFiles('livewire-tmp'));
    }

    public function test_admin_can_search_filter_and_review_packages_without_private_rankings(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $published = BidPackage::factory()->create();
        $draft = BidPackage::factory()->create(['published_at' => null, 'fleet' => '747', 'source_revision' => 'DRAFT_7']);
        $archived = BidPackage::factory()->create(['archived_at' => now()]);
        BidWorkspace::factory()->create(['bid_package_id' => $published->id, 'selections' => ['PRIVATE-RANKING']]);

        Livewire::test(ListBidPackages::class)
            ->assertCanSeeTableRecords([$published, $draft, $archived])
            ->filterTable('status', 'draft')->assertCanSeeTableRecords([$draft])->assertCanNotSeeTableRecords([$published, $archived])
            ->resetTableFilters()->filterTable('status', 'archived')->assertCanSeeTableRecords([$archived])->assertCanNotSeeTableRecords([$published, $draft])
            ->resetTableFilters()->filterTable('fleet', '747')->assertCanSeeTableRecords([$draft])->assertCanNotSeeTableRecords([$published])
            ->resetTableFilters()->searchTable('DRAFT_7')->assertCanSeeTableRecords([$draft])->assertCanNotSeeTableRecords([$published]);

        Livewire::test(ViewBidPackage::class, ['record' => $published->id])
            ->assertSee(['Package preview', 'Source coverage', 'Not imported', 'Saved workspaces'])
            ->assertDontSee('PRIVATE-RANKING');
    }

    public function test_admin_imports_previews_and_publishes_without_retaining_sources(): void
    {
        Storage::fake('local');
        $this->freezeTime();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $this->mockPdf();

        Livewire::test(ListBidPackages::class)->callAction('import', data: ['files' => [$this->pdf()]])
            ->assertHasNoActionErrors();
        $package = BidPackage::query()->sole();
        $this->assertNull($package->published_at);
        $this->assertSame($admin->id, $package->imported_by);
        $this->assertSame('2026-11', $package->period);
        $this->assertCount(1, $package->payload['lines']);
        $this->assertSame([], Storage::disk('local')->allFiles('livewire-tmp'));

        Livewire::test(ViewBidPackage::class, ['record' => $package->id])
            ->assertSee(['Draft', 'Imported', 'Not imported'])
            ->callAction('publish')->assertHasNoActionErrors();
        $this->assertSame(now()->timestamp, $package->fresh()->published_at->timestamp);
    }

    public function test_duplicate_import_reuses_draft_and_published_package(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->mockPdf(calls: 3);
        Livewire::test(ListBidPackages::class)->callAction('import', data: ['files' => [$this->pdf()]]);
        $package = BidPackage::query()->sole();
        Livewire::test(ListBidPackages::class)->callAction('import', data: ['files' => [$this->pdf()]])
            ->assertRedirect(BidPackageResource::getUrl('view', ['record' => $package]));
        Livewire::test(ViewBidPackage::class, ['record' => $package->id])->callAction('publish');
        Livewire::test(ListBidPackages::class)->callAction('import', data: ['files' => [$this->pdf()]])
            ->assertRedirect(BidPackageResource::getUrl('view', ['record' => $package]));
        $this->assertDatabaseCount('bid_packages', 1);
    }

    public function test_owner_replaces_sources_of_unused_draft_without_publishing(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $draft = BidPackage::factory()->create(['published_at' => null, 'imported_by' => $admin->id, 'fleet' => '747']);
        $this->mockPdf();

        Livewire::test(ViewBidPackage::class, ['record' => $draft->id])
            ->callAction('replaceSources', data: ['files' => [$this->pdf()]])->assertHasNoActionErrors();

        $this->assertDatabaseCount('bid_packages', 1);
        $this->assertSame('777', $draft->fresh()->fleet);
        $this->assertCount(1, $draft->fresh()->payload['lines']);
        $this->assertNull($draft->fresh()->published_at);
    }

    public function test_invalid_sources_leave_draft_unchanged_and_show_recovery(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $draft = BidPackage::factory()->create(['published_at' => null, 'imported_by' => $admin->id]);
        $original = $draft->payload;
        $this->mockPdf('Unsupported report');

        Livewire::test(ViewBidPackage::class, ['record' => $draft->id])
            ->callAction('replaceSources', data: ['files' => [$this->pdf()]])
            ->assertHasActionErrors(['files']);

        $this->assertSame($original, $draft->fresh()->payload);
        $this->assertNull($draft->fresh()->published_at);
    }

    public function test_unexpected_parser_failure_reports_error_without_creating_package(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Exceptions::fake();
        $this->mock(ParseBidDocuments::class)->shouldReceive('parse')->once()->andThrow(new RuntimeException('Parser unavailable'));

        Livewire::test(ListBidPackages::class)->callAction('import', data: ['files' => [$this->pdf()]])
            ->assertNotified('Import failed');
        $this->assertDatabaseCount('bid_packages', 0);
        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_replacement_cannot_take_another_packages_fingerprint(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $this->mockPdf(calls: 2);
        Livewire::test(ListBidPackages::class)->callAction('import', data: ['files' => [$this->pdf()]]);
        $existing = BidPackage::query()->sole();
        $draft = BidPackage::factory()->create(['published_at' => null, 'imported_by' => $admin->id]);
        $fingerprint = $draft->fingerprint;

        Livewire::test(ViewBidPackage::class, ['record' => $draft->id])
            ->callAction('replaceSources', data: ['files' => [$this->pdf()]])->assertHasActionErrors(['files']);

        $this->assertSame($fingerprint, $draft->fresh()->fingerprint);
        $this->assertModelExists($existing);
        $this->assertDatabaseCount('bid_packages', 2);
    }

    public function test_owner_can_delete_unused_draft_but_published_and_used_packages_are_protected(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $draft = BidPackage::factory()->create(['published_at' => null, 'imported_by' => $admin->id]);
        Livewire::test(ViewBidPackage::class, ['record' => $draft->id])->callAction('delete')->assertRedirect();
        $this->assertModelMissing($draft);

        $published = BidPackage::factory()->create(['imported_by' => $admin->id]);
        $usedDraft = BidPackage::factory()->create(['published_at' => null, 'imported_by' => $admin->id]);
        $workspace = BidWorkspace::factory()->create(['bid_package_id' => $usedDraft->id]);
        foreach ([$published, $usedDraft] as $package) {
            Livewire::test(ViewBidPackage::class, ['record' => $package->id])
                ->assertActionHidden('delete')->assertActionHidden('replaceSources')->assertActionHidden('publish');
            $this->assertFalse($admin->can('delete', $package));
        }
        $this->assertModelExists($workspace);
        $this->assertFalse($admin->can('deleteAny', BidPackage::class));
    }

    public function test_stale_delete_rechecks_publication_and_preserves_package(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $draft = BidPackage::factory()->create(['published_at' => null, 'imported_by' => $admin->id]);
        $page = Livewire::test(ViewBidPackage::class, ['record' => $draft->id])->mountAction('delete');
        $draft->update(['published_at' => now()]);
        $page->callMountedAction();
        $this->assertModelExists($draft);
    }

    public function test_other_admin_can_view_but_cannot_replace_publish_or_delete_someone_elses_draft(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $draft = BidPackage::factory()->create(['published_at' => null]);
        Livewire::test(ViewBidPackage::class, ['record' => $draft->id])
            ->assertOk()->assertActionHidden('replaceSources')->assertActionHidden('publish')->assertActionHidden('delete');
        $this->expectException(AuthorizationException::class);
        app(DeleteBidPackage::class)->handle($draft->id);
    }

    public function test_archive_preserves_saved_workspace_and_blocks_new_selection_until_restored(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->admin()->create();
        $newUser = User::factory()->admin()->create();
        $package = BidPackage::factory()->create();
        $workspace = BidWorkspace::factory()->create(['user_id' => $owner->id, 'bid_package_id' => $package->id, 'selections' => ['F1']]);
        $this->actingAs($admin);
        Livewire::test(ViewBidPackage::class, ['record' => $package->id])->callAction('archive');
        $this->assertNotNull($package->fresh()->archived_at);
        $this->assertSame(['F1'], $workspace->fresh()->selections);

        Livewire::actingAs($owner)->test(CrewBids::class)->assertSet('packageId', $package->id)
            ->assertViewHas('packages', fn ($packages): bool => $packages->contains('id', $package->id))
            ->call('remove', 'F1')->assertHasNoErrors();
        Livewire::actingAs($newUser)->test(CrewBids::class)->assertSet('packageId', null)
            ->assertViewHas('packages', fn ($packages): bool => $packages->isEmpty())
            ->set('contextPackage', (string) $package->id)->call('changeContext')->assertNotFound();

        Livewire::actingAs($admin)->test(ViewBidPackage::class, ['record' => $package->id])->callAction('restore');
        $this->assertNull($package->fresh()->archived_at);
        Livewire::actingAs($newUser)->test(CrewBids::class)->assertSet('packageId', $package->id);
    }

    public function test_database_prevents_deletion_of_package_with_saved_workspace(): void
    {
        $package = BidPackage::factory()->create();
        $workspace = BidWorkspace::factory()->create(['bid_package_id' => $package->id]);
        try {
            $package->delete();
            $this->fail('Package deletion must be restricted.');
        } catch (QueryException) {
            $this->assertModelExists($package);
            $this->assertModelExists($workspace);
        }
    }

    public function test_import_rejects_invalid_files_and_enforces_shared_rate_limit(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        Livewire::test(ListBidPackages::class)->callAction('import', data: ['files' => []])->assertHasActionErrors(['files' => 'required']);
        Livewire::test(ListBidPackages::class)->callAction('import', data: ['files' => [UploadedFile::fake()->create('notes.txt', 1, 'text/plain')]])->assertHasActionErrors(['files']);
        $this->mock(Parser::class)->shouldNotReceive('parseFile');
        RateLimiter::hit('bid-import:'.$admin->id, 60);
        RateLimiter::hit('bid-import:'.$admin->id, 60);
        RateLimiter::hit('bid-import:'.$admin->id, 60);
        RateLimiter::hit('bid-import:'.$admin->id, 60);
        RateLimiter::hit('bid-import:'.$admin->id, 60);

        Livewire::test(ListBidPackages::class)->callAction('import', data: ['files' => [$this->pdf()]])->assertHasActionErrors(['files']);
        $this->assertDatabaseCount('bid_packages', 0);
    }

    public function test_other_seat_cannot_open_archived_package_without_its_own_workspace(): void
    {
        $admin = User::factory()->admin()->create(['crew_bid_seat_preference' => 'CA']);
        $archived = BidPackage::factory()->create(['archived_at' => now()]);
        BidWorkspace::factory()->create(['user_id' => $admin->id, 'bid_package_id' => $archived->id, 'seat' => 'FO']);

        Livewire::actingAs($admin)->test(CrewBids::class)->assertSet('packageId', null)
            ->set('contextPackage', (string) $archived->id)->call('changeContext')->assertNotFound();
        $this->assertDatabaseMissing('bid_workspaces', ['user_id' => $admin->id, 'bid_package_id' => $archived->id, 'seat' => 'CA']);
    }

    public function test_archived_revisions_are_not_offered_and_cannot_be_opened_by_id(): void
    {
        $current = BidPackage::factory()->create();
        $page = Livewire::actingAs(User::factory()->admin()->create())->test(CrewBids::class);
        $archived = BidPackage::factory()->create(['source_revision' => 'ARCHIVED_REVISION', 'archived_at' => now()]);
        $page->assertSet('packageId', $current->id)->call('$refresh')->assertViewHas('latest', null)
            ->call('reviewRevision', $archived->id)->assertNotFound();
    }

    public function test_non_admin_and_disabled_feature_cannot_access_package_management(): void
    {
        $package = BidPackage::factory()->create();
        $this->get('/admin/bid-packages')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create());
        $this->get('/admin/bid-packages')->assertForbidden();
        $this->get('/admin/bid-packages/'.$package->id)->assertForbidden();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $page = Livewire::test(ListBidPackages::class)->mountAction('import');
        config(['features.crew_bids.enabled' => false]);
        $page->callMountedAction()->assertForbidden();
        $this->get('/admin/bid-packages')->assertForbidden();
        $this->assertFalse($admin->can('create', BidPackage::class));
    }

    public function test_manage_package_links_to_filament_and_source_labels_are_escaped(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $package = BidPackage::factory()->create(['source_revision' => '<script>alert(1)</script>']);
        $this->get(route('crew-bids.index'))->assertOk()->assertSee(BidPackageResource::getUrl(), false);
        Livewire::test(ViewBidPackage::class, ['record' => $package->id])
            ->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('Lines.pdf', "%PDF-1.4\nSynthetic bid source\n%%EOF");
    }

    private function mockPdf(?string $text = null, int $calls = 1): void
    {
        $document = \Mockery::mock(Document::class);
        $document->shouldReceive('getText')->times($calls)->andReturn($text ?? "Lines\nPlan: 202611 / 777 / LINES_4 / 94.PUBLISH\nF1 - 1/2\nBlock time: 4:00\nCredit time: 5:00\n");
        $this->mock(Parser::class)->shouldReceive('parseFile')->times($calls)->andReturn($document);
    }
}
