<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_layout_renders_the_theme_initializer_and_selector(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk()
            ->assertSee('data-theme-initializer', escape: false)
            ->assertSee("localStorage.getItem('theme')", escape: false)
            ->assertSee('data-theme-selector', escape: false)
            ->assertSee('value="system"', escape: false);
    }

    public function test_authenticated_layout_renders_theme_controls_for_desktop_and_mobile_navigation(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('data-theme-initializer', escape: false)
            ->assertSee("localStorage.getItem('theme')", escape: false)
            ->assertSee('data-theme-selector', escape: false)
            ->assertSee('id="desktop-theme-selector"', escape: false)
            ->assertSee('id="mobile-theme-selector"', escape: false);
    }

    public function test_admin_panel_keeps_filament_dark_mode_enabled(): void
    {
        $this->assertTrue(filament()->getPanel('admin')->hasDarkMode());
    }

    public function test_marketing_and_legal_pages_render_the_shared_theme_controls(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('data-theme-initializer', escape: false)
            ->assertSee("localStorage.getItem('theme')", escape: false)
            ->assertSee('data-theme-selector', escape: false)
            ->assertSee('id="welcome-theme-selector"', escape: false)
            ->assertSee('bg-white text-slate-900 dark:bg-slate-900 dark:text-slate-100', escape: false);

        $this->get(route('privacy.policy'))
            ->assertOk()
            ->assertSee('data-theme-initializer', escape: false)
            ->assertSee("localStorage.getItem('theme')", escape: false)
            ->assertSee('data-theme-selector', escape: false)
            ->assertSee('id="privacy-theme-selector"', escape: false)
            ->assertSee('bg-white text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100', escape: false);
    }
}
