<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response
            ->assertStatus(200)
            ->assertSee('New user?')
            ->assertSee('href="'.route('register').'"', false)
            ->assertSee('Register');
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_app_login_and_logout_preserve_the_verified_employee_link(): void
    {
        $user = User::factory()->crewBidsAccess()->create();
        $user->forceFill(['crew_bid_employee_number' => '900000', 'crew_bid_employee_verified_at' => now()])->save();
        $before = $user->fresh()->getRawOriginal('crew_bid_employee_number');

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->assertSame($before, $user->fresh()->getRawOriginal('crew_bid_employee_number'));
        $this->assertSame('900000', $user->fresh()->crew_bid_employee_number);
        $this->assertNotNull($user->fresh()->crew_bid_employee_verified_at);
    }
}
