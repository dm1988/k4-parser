<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_layouts_use_livewires_single_alpine_runtime(): void
    {
        $applicationJavascript = file_get_contents(resource_path('js/app.js'));
        $applicationLayout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $guestLayout = file_get_contents(resource_path('views/layouts/guest.blade.php'));

        $this->assertIsString($applicationJavascript);
        $this->assertIsString($applicationLayout);
        $this->assertIsString($guestLayout);
        $this->assertStringNotContainsString("from 'alpinejs'", $applicationJavascript);
        $this->assertStringNotContainsString('Alpine.start()', $applicationJavascript);
        $this->assertSame(1, substr_count($applicationLayout, '@livewireStyles'));
        $this->assertSame(1, substr_count($applicationLayout, '@livewireScripts'));
        $this->assertSame(1, substr_count($guestLayout, '@livewireStyles'));
        $this->assertSame(1, substr_count($guestLayout, '@livewireScripts'));
    }

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
            ->assertSee('id="mobile-theme-selector"', escape: false)
            ->assertSeeInOrder([
                'data-theme-menu',
                'id="desktop-theme-selector"',
                route('profile.edit'),
            ], escape: false);
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
