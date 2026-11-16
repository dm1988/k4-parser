<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_can_update_user_fields_role_and_active_status_without_changing_password(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill([
            'role' => 'admin',
            'is_active' => true,
        ])->save();

        $targetUser = User::factory()->create([
            'name' => 'Before Name',
            'email' => 'before@example.com',
            'email_verified_at' => null,
        ]);
        $targetUser->forceFill([
            'role' => 'user',
            'is_active' => true,
        ])->save();

        $originalPassword = $targetUser->password;

        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $targetUser->getKey()])
            ->fillForm([
                'name' => 'After Name',
                'email' => 'after@example.com',
                'email_verified_at' => '2026-06-30 12:00:00',
                'password' => '',
                'password_confirmation' => '',
                'role' => 'admin',
                'is_active' => false,
                'has_bought_coffee' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $targetUser->refresh();

        $this->assertSame('After Name', $targetUser->name);
        $this->assertSame('after@example.com', $targetUser->email);
        $this->assertNotNull($targetUser->email_verified_at);
        $this->assertSame($originalPassword, $targetUser->password);
        $this->assertSame('admin', $targetUser->role);
        $this->assertFalse((bool) $targetUser->is_active);
        $this->assertTrue($targetUser->has_bought_coffee);
    }

    public function test_admins_can_reactivate_inactive_users_from_the_form(): void
    {
        $this->actingAs($this->makeAdminUser());
        $inactiveUser = User::factory()->inactive()->create();

        Livewire::test(EditUser::class, ['record' => $inactiveUser->getKey()])
            ->fillForm([
                'is_active' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue((bool) $inactiveUser->fresh()->is_active);
    }

    public function test_admins_can_grant_and_revoke_crew_bid_access_without_changing_role(): void
    {
        $this->actingAs($this->makeAdminUser());
        $targetUser = User::factory()->create();

        Livewire::test(EditUser::class, ['record' => $targetUser->getKey()])
            ->fillForm(['crew_bids_access' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($targetUser->fresh()->crew_bids_access);
        $this->assertSame('user', $targetUser->role);

        Livewire::test(EditUser::class, ['record' => $targetUser->getKey()])
            ->fillForm(['crew_bids_access' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($targetUser->fresh()->crew_bids_access);
        $this->assertSame('user', $targetUser->role);
    }

    public function test_users_table_displays_active_crew_bid_and_coffee_statuses(): void
    {
        $this->actingAs($this->makeAdminUser());
        $activeUser = User::factory()->create();
        $inactiveUser = User::factory()->inactive()->create();
        $purchaser = User::factory()->boughtCoffee()->create();
        $bidder = User::factory()->crewBidsAccess()->create();

        Livewire::test(ListUsers::class)
            ->assertTableColumnStateSet('is_active', true, $activeUser)
            ->assertTableColumnStateSet('is_active', false, $inactiveUser)
            ->assertTableColumnStateSet('crew_bids_access', true, $bidder)
            ->assertTableColumnStateSet('crew_bids_access', false, $activeUser)
            ->assertTableColumnStateSet('has_bought_coffee', true, $purchaser)
            ->assertTableColumnStateSet('has_bought_coffee', false, $activeUser);
    }

    public function test_admins_can_not_deactivate_themselves_from_the_form(): void
    {
        $admin = $this->makeAdminUser();
        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $admin->getKey()])
            ->assertFormFieldDisabled('is_active')
            ->fillForm([
                'is_active' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue((bool) $admin->fresh()->is_active);
    }

    public function test_super_admin_promotion_is_rejected_by_the_filament_user_resource(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill([
            'role' => 'admin',
            'is_active' => true,
        ])->save();

        $targetUser = User::factory()->create();
        $targetUser->forceFill([
            'role' => 'user',
            'is_active' => true,
        ])->save();

        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $targetUser->getKey()])
            ->fillForm([
                'name' => $targetUser->name,
                'email' => $targetUser->email,
                'email_verified_at' => optional($targetUser->email_verified_at)?->format('Y-m-d H:i:s'),
                'password' => '',
                'password_confirmation' => '',
                'role' => 'super_admin',
            ])
            ->call('save')
            ->assertHasFormErrors(['role']);

        $this->assertSame('user', $targetUser->fresh()->role);
    }

    public function test_admins_can_demote_other_admins_without_altering_their_access_grant(): void
    {
        $this->actingAs($this->makeAdminUser());
        $target = User::factory()->admin()->crewBidsAccess()->create();

        Livewire::test(EditUser::class, ['record' => $target->id])->fillForm(['role' => 'user'])
            ->call('save')->assertHasNoFormErrors();

        $this->assertSame('user', $target->fresh()->role);
        $this->assertTrue($target->fresh()->crew_bids_access);
        $this->assertFalse($target->fresh()->canAccessPanel(Filament::getPanel('admin')));
        $this->assertTrue($target->fresh()->canUseCrewBids());
    }

    public function test_promoting_an_active_verified_user_enables_admin_panel_access(): void
    {
        $this->actingAs($this->makeAdminUser());
        $target = User::factory()->create();

        Livewire::test(EditUser::class, ['record' => $target->id])->fillForm(['role' => 'admin'])
            ->call('save')->assertHasNoFormErrors();

        $this->assertTrue($target->fresh()->canAccessPanel(Filament::getPanel('admin')));
        $this->assertTrue($target->fresh()->canUseCrewBids());
        $this->assertFalse($target->fresh()->crew_bids_access);
    }

    public function test_self_role_and_existing_super_admin_roles_cannot_be_changed_through_the_form(): void
    {
        $admin = $this->makeAdminUser();
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $admin->id])->assertFormFieldDisabled('role')
            ->fillForm(['role' => 'user'])->call('save')->assertHasNoFormErrors();
        Livewire::test(EditUser::class, ['record' => $superAdmin->id])->assertFormFieldDisabled('role')
            ->fillForm(['role' => 'user'])->call('save')->assertHasFormErrors(['role']);

        $this->assertSame('admin', $admin->fresh()->role);
        $this->assertSame('super_admin', $superAdmin->fresh()->role);
    }

    public function test_admins_can_search_users_by_name_and_email(): void
    {
        $this->actingAs($this->makeAdminUser());
        $matchingUser = User::factory()->create([
            'name' => 'Searchable Pilot',
            'email' => 'pilot@example.com',
        ]);
        $otherUser = User::factory()->create([
            'name' => 'Different Person',
            'email' => 'different@example.com',
        ]);

        Livewire::test(ListUsers::class)
            ->searchTable('pilot@example.com')
            ->assertCanSeeTableRecords([$matchingUser])
            ->assertCanNotSeeTableRecords([$otherUser])
            ->searchTable('Searchable Pilot')
            ->assertCanSeeTableRecords([$matchingUser])
            ->assertCanNotSeeTableRecords([$otherUser]);
    }

    public function test_admins_can_update_a_user_password_with_confirmation(): void
    {
        $this->actingAs($this->makeAdminUser());
        $targetUser = User::factory()->create();

        Livewire::test(EditUser::class, ['record' => $targetUser->getKey()])
            ->fillForm([
                'name' => $targetUser->name,
                'email' => $targetUser->email,
                'password' => 'A-stronger-password-123!',
                'password_confirmation' => 'A-stronger-password-123!',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check('A-stronger-password-123!', $targetUser->fresh()->password));
    }

    public function test_user_form_rejects_duplicate_email_and_invalid_password_confirmation(): void
    {
        $this->actingAs($this->makeAdminUser());
        $existingUser = User::factory()->create(['email' => 'existing@example.com']);
        $targetUser = User::factory()->create();

        Livewire::test(EditUser::class, ['record' => $targetUser->getKey()])
            ->fillForm([
                'name' => $targetUser->name,
                'email' => $existingUser->email,
                'password' => 'A-stronger-password-123!',
                'password_confirmation' => 'does-not-match',
            ])
            ->call('save')
            ->assertHasFormErrors([
                'email' => 'unique',
                'password' => 'same',
            ]);
    }

    public function test_non_admin_users_can_not_access_user_resource_pages(): void
    {
        $targetUser = User::factory()->create();
        $this->actingAs(User::factory()->crewBidsAccess()->create());

        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/users/create')->assertForbidden();
        $this->get("/admin/users/{$targetUser->getKey()}/edit")->assertForbidden();
    }

    public function test_admins_can_confirm_and_delete_other_users(): void
    {
        $admin = $this->makeAdminUser();
        $this->actingAs($admin);
        $targetUser = User::factory()->create();

        Livewire::test(ListUsers::class)
            ->assertTableActionVisible('edit', $targetUser)
            ->assertTableActionVisible('delete', $targetUser)
            ->mountTableAction('delete', $targetUser)
            ->assertMountedActionModalSee('Delete user')
            ->assertMountedActionModalSee($targetUser->name)
            ->assertMountedActionModalSee($targetUser->email)
            ->callMountedTableAction();

        $this->assertModelMissing($targetUser);
    }

    public function test_admins_cannot_create_users_delete_themselves_or_delete_users_in_bulk(): void
    {
        $admin = $this->makeAdminUser();
        $this->actingAs($admin);
        $targetUser = User::factory()->create();

        $this->get('/admin/users/create')->assertForbidden();

        Livewire::test(ListUsers::class)
            ->assertTableActionHidden('delete', $admin)
            ->assertTableActionVisible('delete', $targetUser)
            ->assertTableBulkActionHidden('delete');

        Livewire::test(EditUser::class, ['record' => $targetUser->getKey()])
            ->assertActionVisible('delete');

        Livewire::test(EditUser::class, ['record' => $admin->getKey()])
            ->assertActionHidden('delete');
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
