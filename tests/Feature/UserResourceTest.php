<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
