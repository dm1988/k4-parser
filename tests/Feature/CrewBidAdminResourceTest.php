<?php

namespace Tests\Feature;

use App\Actions\CrewBids\ImportPilotSeniority;
use App\Filament\Resources\CrewBidSnapshots\CrewBidSnapshotResource;
use App\Filament\Resources\CrewBidSnapshots\Pages\ListCrewBidSnapshots;
use App\Filament\Resources\CrewBidSnapshots\Pages\ViewCrewBidSnapshot;
use App\Filament\Resources\PilotSeniorityLists\Pages\ListPilotSeniorityLists;
use App\Filament\Resources\PilotSeniorityLists\Pages\ViewPilotSeniorityList;
use App\Filament\Resources\PilotSeniorityLists\PilotSeniorityListResource;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\BidPackage;
use App\Models\BidWorkspace;
use App\Models\CrewBidSnapshot;
use App\Models\PilotSeniorityList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class CrewBidAdminResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_import_uses_the_existing_action_and_displays_dated_roster_metadata(): void
    {
        $admin = User::factory()->admin()->create();
        $list = PilotSeniorityList::factory()->create();
        $url = 'https://crewbids.kalittaair.com/hyperlinks/Seniority.pdf';
        $this->actingAs($admin);
        $this->mock(ImportPilotSeniority::class)->shouldReceive('handle')->once()
            ->withArgs(fn (string $source, User $actor): bool => $source === $url && $actor->id === $admin->id)->andReturn($list);

        Livewire::test(ListPilotSeniorityLists::class)->assertCanSeeTableRecords([$list])
            ->assertTableColumnStateSet('pilot_count', 4, $list)
            ->callAction('importSeniority', data: ['url' => $url])
            ->assertHasNoActionErrors()->assertNotified('Seniority list imported')
            ->assertRedirect(PilotSeniorityListResource::getUrl('view', ['record' => $list]));
    }

    public function test_invalid_import_input_and_failed_import_preserve_the_existing_roster(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $list = PilotSeniorityList::factory()->create();
        $before = $list->fresh()->getRawOriginal();
        $this->mock(ImportPilotSeniority::class)->shouldReceive('handle')->once()->andThrow(new RuntimeException('private upstream detail'));

        Livewire::test(ListPilotSeniorityLists::class)->callAction('importSeniority', data: ['url' => 'not a URL'])
            ->assertHasActionErrors(['url']);
        Livewire::test(ListPilotSeniorityLists::class)->callAction('importSeniority', data: ['url' => 'https://crewbids.kalittaair.com/hyperlinks/Seniority.pdf'])
            ->assertNotified('Seniority import failed')->assertDontSee('private upstream detail');

        $this->assertSame($before, $list->fresh()->getRawOriginal());
    }

    public function test_roster_table_searches_employee_numbers_and_names_and_paginates_without_editing_source_data(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $pilots = [];
        for ($rank = 1; $rank <= 30; $rank++) {
            $pilots[(string) (910000 + $rank)] = ['name' => 'Synthetic, Pilot '.$rank, 'seniority' => $rank];
        }
        $list = PilotSeniorityList::factory()->create(['pilots' => $pilots]);

        Livewire::test(ViewPilotSeniorityList::class, ['record' => $list->id])
            ->assertSee('Current pilot seniority list')->assertCanSeeTableRecords(['910001'])->assertCanNotSeeTableRecords(['910030'])
            ->call('gotoPage', 2)->assertCanSeeTableRecords(['910030'])->assertCanNotSeeTableRecords(['910001'])
            ->searchTable('910030')->assertCanSeeTableRecords(['910030'])->assertCanNotSeeTableRecords(['910001'])
            ->searchTable('Synthetic, Pilot 29')->assertCanSeeTableRecords(['910029'])->assertCanNotSeeTableRecords(['910030']);

        $this->assertSame($pilots, $list->fresh()->pilots);
        $this->assertFalse(Gate::allows('update', $list));
        $this->assertFalse(Gate::allows('delete', $list));
        $this->get(PilotSeniorityListResource::getUrl('index').'/'.$list->id.'/edit')->assertNotFound();
    }

    public function test_snapshot_admin_views_preserve_context_and_choice_order_and_link_to_protected_refresh(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $package = BidPackage::factory()->create();
        $snapshot = CrewBidSnapshot::factory()->create(['retrieved_at' => now()->subHour(), 'bidders' => [
            '910001' => ['name' => 'Synthetic, Pilot', 'seniority' => 10, 'numbers' => [2, 1, 2]],
        ]]);
        $other = CrewBidSnapshot::factory()->create(['seat' => 'CA']);

        Livewire::test(ListCrewBidSnapshots::class)->assertCanSeeTableRecords([$snapshot, $other])
            ->assertTableColumnStateSet('freshness', 'Stale', $snapshot)
            ->filterTable('seat', 'FO')->assertCanSeeTableRecords([$snapshot])->assertCanNotSeeTableRecords([$other]);
        Livewire::test(ViewCrewBidSnapshot::class, ['record' => $snapshot->id])->assertSee('2, 1, 2')->assertSee('Stale—refresh recommended')
            ->assertActionHasUrl('refreshSharedBids', route('crew-bids.employees.index', ['package' => $package, 'seat' => 'FO']))
            ->searchTable('910001')->assertCanSeeTableRecords(['910001']);

        $this->assertFalse(Gate::allows('update', $snapshot));
        $this->assertFalse(Gate::allows('delete', $snapshot));
        $this->assertSame([2, 1, 2], $snapshot->fresh()->bidders['910001']['numbers']);
    }

    public function test_snapshot_refresh_requires_a_published_unarchived_matching_package(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $snapshot = CrewBidSnapshot::factory()->create();
        BidPackage::factory()->create(['published_at' => null]);
        BidPackage::factory()->create(['archived_at' => now()]);
        BidPackage::factory()->create(['fleet' => '747']);

        Livewire::test(ViewCrewBidSnapshot::class, ['record' => $snapshot->id])->assertActionDisabled('refreshSharedBids');
    }

    #[DataProvider('deniedAccess')]
    public function test_non_admin_and_inactive_or_unverified_accounts_cannot_read_new_admin_resources(string $state): void
    {
        $list = PilotSeniorityList::factory()->create();
        $snapshot = CrewBidSnapshot::factory()->create();
        $attributes = match ($state) {
            'inactive admin' => ['role' => 'admin', 'is_active' => false],
            'unverified admin' => ['role' => 'admin', 'email_verified_at' => null],
            default => ['role' => 'user', 'crew_bids_access' => true],
        };
        if ($state !== 'guest') {
            $this->actingAs(User::factory()->create($attributes));
        }
        $urls = [PilotSeniorityListResource::getUrl('index'), PilotSeniorityListResource::getUrl('view', ['record' => $list]),
            CrewBidSnapshotResource::getUrl('index'), CrewBidSnapshotResource::getUrl('view', ['record' => $snapshot])];
        foreach ($urls as $url) {
            $response = $this->get($url);
            if ($state === 'guest') {
                $response->assertRedirect();
            } else {
                $response->assertForbidden();
            }
        }
    }

    public static function deniedAccess(): array
    {
        return ['guest' => ['guest'], 'regular bidder' => ['bidder'], 'inactive admin' => ['inactive admin'], 'unverified admin' => ['unverified admin']];
    }

    public function test_employee_link_is_displayed_but_cannot_be_changed_through_the_user_form(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $user = User::factory()->create();
        $user->forceFill(['crew_bid_employee_number' => '910001', 'crew_bid_employee_verified_at' => now()])->save();

        Livewire::test(ListUsers::class)->assertTableColumnStateSet('crew_bid_employee_number', '910001', $user);
        Livewire::test(EditUser::class, ['record' => $user->id])->assertFormFieldDisabled('crew_bid_employee_number')
            ->assertFormFieldDisabled('crew_bid_employee_verified_at')->fillForm(['crew_bid_employee_number' => '910002', 'crew_bid_employee_verified_at' => null])
            ->call('save')->assertHasNoFormErrors();

        $this->assertSame('910001', $user->fresh()->crew_bid_employee_number);
        $this->assertNotNull($user->fresh()->crew_bid_employee_verified_at);
    }

    public function test_admin_unlinks_only_the_target_identity_while_preserving_private_bids_and_shared_data(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $user = User::factory()->create();
        $other = User::factory()->create();
        foreach ([$user, $other] as $linked) {
            $linked->forceFill(['crew_bid_employee_number' => '910001', 'crew_bid_employee_verified_at' => now()])->save();
        }
        $workspace = BidWorkspace::factory()->create(['user_id' => $user->id]);
        $before = $workspace->fresh()->getRawOriginal();
        $snapshot = CrewBidSnapshot::factory()->create();

        Livewire::test(EditUser::class, ['record' => $user->id])->mountAction('unlinkEmployee')
            ->assertMountedActionModalSee('Personal predictions will be unavailable')->callMountedAction()->assertNotified('Employee link removed')
            ->assertFormSet(['crew_bid_employee_number' => null, 'crew_bid_employee_verified_at' => null])->assertActionHidden('unlinkEmployee');

        $this->assertNull($user->fresh()->crew_bid_employee_number);
        $this->assertNull($user->fresh()->crew_bid_employee_verified_at);
        $this->assertSame('910001', $other->fresh()->crew_bid_employee_number);
        $this->assertSame($before, $workspace->fresh()->getRawOriginal());
        $this->assertModelExists($snapshot);
    }

    public function test_roster_and_snapshot_names_are_escaped_in_admin_tables(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $name = '<img src=x onerror=alert(1)>';
        $list = PilotSeniorityList::factory()->create(['pilots' => ['910001' => ['name' => $name, 'seniority' => 1]]]);
        $snapshot = CrewBidSnapshot::factory()->create(['bidders' => ['910001' => ['name' => $name, 'seniority' => 1, 'numbers' => [1]]]]);

        Livewire::test(ViewPilotSeniorityList::class, ['record' => $list->id])->assertSee($name)->assertDontSee($name, false);
        Livewire::test(ViewCrewBidSnapshot::class, ['record' => $snapshot->id])->assertSee($name)->assertDontSee($name, false);
    }
}
