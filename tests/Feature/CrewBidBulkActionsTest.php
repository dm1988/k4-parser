<?php

namespace Tests\Feature;

use App\Actions\CrewBids\SaveBidWorkspace;
use App\Livewire\CrewBids;
use App\Models\BidPackage;
use App\Models\BidWorkspace;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class CrewBidBulkActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_mode_replaces_dragging_with_checkboxes_and_places_bulk_controls_outside_the_scroll_area(): void
    {
        [$user, $workspace] = $this->workspace();
        $page = Livewire::actingAs($user)->test(CrewBids::class)->set('showList', true);
        $normal = $this->document($page->html());
        $this->assertTrue($normal->query('//*[@id="bid-list-items"]')->item(0)->hasAttribute('wire:sort'));
        $menu = $normal->query('//*[@id="bid-list-drawer"]/section/footer//*[@id="bid-list-more-actions"]')->item(0);
        $this->assertNotNull($menu);
        $this->assertStringContainsString('Sync from K4', $menu->textContent);
        $this->assertStringContainsString('Add all 5 matching flying lines', $menu->textContent);
        $this->assertStringContainsString('Clear bid list', $menu->textContent);

        $page->call('toggleListEditMode')->assertSet('editingList', true)->assertSee('0 selected')->assertSee('Move to top')->assertSee('Move to bottom')->assertSee('Remove selected');

        $editing = $this->document($page->html());
        $this->assertFalse($editing->query('//*[@id="bid-list-items"]')->item(0)->hasAttribute('wire:sort'));
        $this->assertSame(5, $editing->query('//*[@id="bid-list-items"]//input[@type="checkbox"]')->length);
        $this->assertSame('Select F1 for bulk actions', $editing->query('//*[@id="bid-list-items"]//input')->item(0)->getAttribute('aria-label'));
        $this->assertSame(1, $editing->query('//*[@id="bid-list-drawer"]/section/section[@aria-label="Bid list bulk actions"]')->length);
        $this->assertStringNotContainsString('wire:sort:handle', $page->html());
        $this->assertSame(1, $workspace->fresh()->revision);
        $page->set('checkedSelections', ['F1'])->call('toggleListEditMode')->assertSet('editingList', false)->assertSet('checkedSelections', []);
        $restored = $this->document($page->html());
        $this->assertTrue($restored->query('//*[@id="bid-list-items"]')->item(0)->hasAttribute('wire:sort'));
        $this->assertSame(0, $restored->query('//*[@id="bid-list-items"]//input[@type="checkbox"]')->length);
    }

    public function test_nonadjacent_rows_move_in_list_order_and_stay_checked_for_followup_actions(): void
    {
        [$user, $workspace] = $this->workspace();
        $other = BidWorkspace::factory()->create(['bid_package_id' => $workspace->bid_package_id, 'selections' => ['F1']]);
        $preferences = $workspace->preferences;
        $page = Livewire::actingAs($user)->test(CrewBids::class)->call('toggleListEditMode')->set('checkedSelections', ['F4', 'R2']);

        $page->call('moveCheckedToTop')->assertHasNoErrors()->assertSet('selections', ['R2', 'F4', 'F1', 'F3', 'F5'])
            ->assertSet('checkedSelections', ['F4', 'R2'])->assertSet('editingList', true)->assertSee('2 selected');

        $this->assertSame(['R2', 'F4', 'F1', 'F3', 'F5'], $workspace->fresh()->selections);
        $this->assertSame(2, $workspace->fresh()->revision);
        $page->call('moveCheckedToBottom')->assertHasNoErrors()->assertSet('selections', ['F1', 'F3', 'F5', 'R2', 'F4'])
            ->assertSet('checkedSelections', ['F4', 'R2'])->assertSet('editingList', true);
        $this->assertSame(['F1', 'F3', 'F5', 'R2', 'F4'], $workspace->fresh()->selections);
        $this->assertSame(3, $workspace->fresh()->revision);
        $this->assertSame($preferences, $workspace->fresh()->preferences);
        $this->assertSame(['F1'], $other->fresh()->selections);
        $this->assertDatabaseCount('bid_submissions', 0);
    }

    public function test_bulk_remove_deletes_only_checked_rows_in_one_save_and_clears_only_removed_checks(): void
    {
        [$user, $workspace] = $this->workspace();

        Livewire::actingAs($user)->test(CrewBids::class)->call('toggleListEditMode')->set('checkedSelections', ['F3', 'F1'])->call('removeChecked')
            ->assertHasNoErrors()->assertSet('selections', ['R2', 'F4', 'F5'])->assertSet('checkedSelections', [])
            ->assertSet('editingList', true)->assertSee('0 selected')->assertSee('2 choices removed');

        $this->assertSame(['R2', 'F4', 'F5'], $workspace->fresh()->selections);
        $this->assertSame(2, $workspace->fresh()->revision);
    }

    public function test_single_removal_and_clear_list_prune_checked_rows(): void
    {
        [$user, $workspace] = $this->workspace();

        Livewire::actingAs($user)->test(CrewBids::class)->call('toggleListEditMode')->set('checkedSelections', ['F1', 'F3'])
            ->call('remove', 'F1')->assertSet('checkedSelections', ['F3'])
            ->call('clearList')->assertSet('checkedSelections', [])->assertSet('selections', []);

        $this->assertSame([], $workspace->fresh()->selections);
    }

    #[DataProvider('bulkActions')]
    public function test_empty_selection_is_a_noop(string $action): void
    {
        [$user, $workspace] = $this->workspace();
        $original = $workspace->fresh()->getAttributes();

        Livewire::actingAs($user)->test(CrewBids::class)->call('toggleListEditMode')->call($action)->assertHasNoErrors()->assertSet('checkedSelections', []);

        $this->assertSame($original, $workspace->fresh()->getAttributes());
    }

    #[DataProvider('bulkActions')]
    public function test_bulk_actions_require_edit_mode(string $action): void
    {
        [$user, $workspace] = $this->workspace();
        $original = $workspace->fresh()->getAttributes();

        Livewire::actingAs($user)->test(CrewBids::class)->set('checkedSelections', ['F3'])->call($action)->assertStatus(422);

        $this->assertSame($original, $workspace->fresh()->getAttributes());
    }

    public static function bulkActions(): array
    {
        return ['top' => ['moveCheckedToTop'], 'bottom' => ['moveCheckedToBottom'], 'remove' => ['removeChecked']];
    }

    public function test_selecting_all_can_move_without_rewriting_unchanged_order_then_remove_the_complete_list(): void
    {
        [$user, $workspace] = $this->workspace();
        $page = Livewire::actingAs($user)->test(CrewBids::class)->call('toggleListEditMode')->set('checkedSelections', $workspace->selections);

        $page->call('moveCheckedToTop')->assertHasNoErrors()->call('moveCheckedToBottom')->assertHasNoErrors();

        $this->assertSame(1, $workspace->fresh()->revision);
        $page->call('removeChecked')->assertHasNoErrors()->assertSet('selections', [])->assertSet('checkedSelections', [])->assertSet('editingList', true);
        $this->assertSame([], $workspace->fresh()->selections);
        $this->assertSame(2, $workspace->fresh()->revision);
    }

    #[DataProvider('invalidSelections')]
    public function test_invalid_or_out_of_list_checks_are_rejected_without_mutating_saved_choices(array $checked, string $error): void
    {
        [$user, $workspace] = $this->workspace();
        $original = $workspace->fresh()->getAttributes();

        Livewire::actingAs($user)->test(CrewBids::class)->call('toggleListEditMode')->set('checkedSelections', $checked)
            ->call('moveCheckedToTop')->assertHasErrors($error)->assertSet('selections', $workspace->selections)
            ->call('toggleListEditMode')->assertHasNoErrors()->assertSet('checkedSelections', []);

        $this->assertSame($original, $workspace->fresh()->getAttributes());
    }

    public static function invalidSelections(): array
    {
        return [
            'available but absent from list' => [['F6'], 'checkedSelections.0'],
            'unknown line' => [['F99'], 'checkedSelections.0'],
            'duplicate line' => [['F1', 'F1'], 'checkedSelections.0'],
            'nonstring line' => [[1], 'checkedSelections.0'],
            'nested data' => [[['F1']], 'checkedSelections.0'],
            'not a list' => [['choice' => 'F1'], 'checkedSelections'],
        ];
    }

    public function test_conflicting_save_preserves_checked_local_order_and_exposes_the_error_inside_the_drawer(): void
    {
        [$user, $workspace] = $this->workspace();
        $page = Livewire::actingAs($user)->test(CrewBids::class)->call('toggleListEditMode')->set('checkedSelections', ['F4', 'R2']);
        $workspace->update(['revision' => 2, 'selections' => ['F5', 'F4', 'F3', 'R2', 'F1']]);

        $page->call('moveCheckedToTop')->assertHasErrors('workspace')->assertSet('dirty', true)->assertSet('revision', 1)
            ->assertSet('selections', ['R2', 'F4', 'F1', 'F3', 'F5'])->assertSet('checkedSelections', ['F4', 'R2']);

        $this->assertSame(['F5', 'F4', 'F3', 'R2', 'F1'], $workspace->fresh()->selections);
        $toolbar = $this->document($page->html())->query('//*[@aria-label="Bid list bulk actions"]')->item(0);
        $this->assertStringContainsString('This list changed in another tab.', $toolbar->textContent);
        $page->call('reloadSaved')->assertSet('dirty', false)->assertSet('editingList', false)->assertSet('checkedSelections', [])
            ->assertSet('selections', ['F5', 'F4', 'F3', 'R2', 'F1']);
    }

    public function test_failed_bulk_save_retains_checks_and_can_retry_without_reapplying_the_move(): void
    {
        [$user, $workspace] = $this->workspace();
        $page = Livewire::actingAs($user)->test(CrewBids::class)->call('toggleListEditMode')->set('checkedSelections', ['F4', 'R2']);
        Exceptions::fake();
        $this->mock(SaveBidWorkspace::class)->shouldReceive('handle')->once()->andThrow(new RuntimeException('Simulated database outage'));

        $page->call('moveCheckedToBottom')->assertSet('dirty', true)->assertHasErrors('workspace')
            ->assertSet('selections', ['F1', 'F3', 'F5', 'R2', 'F4'])->assertSet('checkedSelections', ['F4', 'R2']);

        $this->assertSame(['F1', 'R2', 'F3', 'F4', 'F5'], $workspace->fresh()->selections);
        Exceptions::assertReported(RuntimeException::class);
        $this->app->forgetInstance(SaveBidWorkspace::class);
        $page->call('retrySave')->assertHasNoErrors()->assertSet('dirty', false)->assertSet('checkedSelections', ['F4', 'R2']);
        $this->assertSame(['F1', 'F3', 'F5', 'R2', 'F4'], $workspace->fresh()->selections);
        $this->assertSame(2, $workspace->fresh()->revision);
    }

    public function test_context_changes_clear_edit_mode_and_checks_without_changing_either_list(): void
    {
        [$user, $workspace] = $this->workspace();
        $package = BidPackage::factory()->create(['period' => '2026-12']);
        $other = BidWorkspace::factory()->create(['user_id' => $user->id, 'bid_package_id' => $package->id, 'selections' => ['R2', 'F1'], 'updated_at' => now()->subDay()]);

        Livewire::actingAs($user)->test(CrewBids::class)->call('toggleListEditMode')->set('checkedSelections', ['F4'])
            ->set('contextPackage', (string) $package->id)->call('changeContext')->assertHasNoErrors()
            ->assertSet('editingList', false)->assertSet('checkedSelections', [])->assertSet('selections', ['R2', 'F1']);

        $this->assertSame(1, $workspace->fresh()->revision);
        $this->assertSame(['R2', 'F1'], $other->fresh()->selections);
        $this->assertSame(0, $other->fresh()->revision);
    }

    public function test_revoked_access_blocks_bulk_mutations(): void
    {
        [$user, $workspace] = $this->workspace();
        $page = Livewire::actingAs($user)->test(CrewBids::class)->call('toggleListEditMode')->set('checkedSelections', ['F4']);
        $user->update(['crew_bids_access' => false]);

        $page->call('removeChecked')->assertForbidden();

        $this->assertSame(['F1', 'R2', 'F3', 'F4', 'F5'], $workspace->fresh()->selections);
        $this->assertSame(1, $workspace->fresh()->revision);
    }

    /** @return array{User, BidWorkspace} */
    private function workspace(): array
    {
        $user = User::factory()->crewBidsAccess()->create();
        $package = BidPackage::factory()->create();
        $payload = $package->payload;
        foreach (['F3', 'F4', 'F5', 'F6'] as $id) {
            $payload['lines'][$id] = array_replace($payload['lines']['F1'], ['id' => $id]);
        }
        $package->update(['payload' => $payload]);
        $workspace = BidWorkspace::factory()->create(['user_id' => $user->id, 'bid_package_id' => $package->id,
            'selections' => ['F1', 'R2', 'F3', 'F4', 'F5'], 'revision' => 1]);

        return [$user, $workspace];
    }

    private function document(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML($html);

        return new DOMXPath($document);
    }
}
