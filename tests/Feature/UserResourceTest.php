<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_can_update_only_safe_user_fields_without_changing_password_or_role(): void
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
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $targetUser->refresh();

        $this->assertSame('After Name', $targetUser->name);
        $this->assertSame('after@example.com', $targetUser->email);
        $this->assertNotNull($targetUser->email_verified_at);
        $this->assertSame($originalPassword, $targetUser->password);
        $this->assertSame('user', $targetUser->role);
        $this->assertTrue((bool) $targetUser->is_active);
    }

    public function test_admin_promotion_remains_manual_only_from_the_filament_user_resource(): void
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
            ->assertHasNoFormErrors();

        $this->assertSame('user', $targetUser->fresh()->role);
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
        $this->actingAs(User::factory()->create());

        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/users/create')->assertForbidden();
        $this->get("/admin/users/{$targetUser->getKey()}/edit")->assertForbidden();
    }

    public function test_user_create_and_delete_actions_are_hidden_from_admins(): void
    {
        $this->actingAs($this->makeAdminUser());
        $targetUser = User::factory()->create();

        $this->get('/admin/users/create')->assertForbidden();

        Livewire::test(ListUsers::class)
            ->assertTableActionVisible('edit', $targetUser)
            ->assertTableBulkActionHidden('delete');

        Livewire::test(EditUser::class, ['record' => $targetUser->getKey()])
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
