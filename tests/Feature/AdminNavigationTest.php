<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_eligible_admins_can_see_the_admin_navigation_link(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill([
            'role' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSeeText('Admin Panel')
            ->assertSee(route('filament.admin.pages.dashboard'), escape: false);
    }

    public function test_non_admin_users_can_not_see_the_admin_navigation_link(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSeeText('Admin Panel')
            ->assertDontSee(route('filament.admin.pages.dashboard'), escape: false);
    }

    public function test_inactive_or_unverified_admins_can_not_see_the_admin_navigation_link(): void
    {
        foreach ([
            ['is_active' => false, 'email_verified_at' => now()],
            ['is_active' => true, 'email_verified_at' => null],
        ] as $attributes) {
            $admin = User::factory()->create();
            $admin->forceFill(array_merge([
                'role' => 'admin',
            ], $attributes))->save();

            $this->actingAs($admin)
                ->get('/dashboard')
                ->assertOk()
                ->assertDontSeeText('Admin Panel')
                ->assertDontSee(route('filament.admin.pages.dashboard'), escape: false);
        }
    }
}
