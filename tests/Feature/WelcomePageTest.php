<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WelcomePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'features.schedule_extractor.enabled' => true,
            'features.schedule_extractor.for_all_users' => true,
            'features.flight_release.enabled' => true,
            'features.flight_release.for_all_users' => false,
        ]);
    }

    public function test_welcome_presents_both_tools_under_the_crew_compass_brand(): void
    {
        $response = $this->get(route('welcome'));

        $response->assertOk()
            ->assertSee('<title>K4 Extractor | Crew Compass</title>', escape: false)
            ->assertSee('name="description"', escape: false)
            ->assertSeeText('Crew Compass')
            ->assertSeeText('Turn Crew Documents into Actionable Flight Data')
            ->assertSeeText('Schedule Extractor')
            ->assertSeeText('Flight Plan Extractor')
            ->assertSeeText('Flight Plan Brief')
            ->assertSeeText('Jeppesen Crew Access')
            ->assertSeeText('Export events to your personal calendar.')
            ->assertSeeText('Check extracted information against the source release.')
            ->assertDontSeeText('Extract your JCA schedule instantly')
            ->assertDontSeeText('Schedule Management Simplified');

        $html = $response->getContent();
        $this->assertIsString($html);
        $this->assertSame(1, preg_match_all('/<h1\b/', $html));
        $this->assertSame(2, preg_match_all('/<article\b/', $html));
        $response->assertSeeInOrder([
            '<h2 id="schedule-extractor-title"',
            '<h2 id="flight-plan-extractor-title"',
            'data-demo-badge',
            'Inside Schedule Extractor',
            'images/iphone_screenshot.PNG',
        ], escape: false);
        $this->assertSame(1, preg_match_all('/\sdata-demo-badge(?:=|\s|>)/', $html));
    }

    public function test_guest_actions_offer_login_and_registration_without_tool_access(): void
    {
        $this->get(route('welcome'))
            ->assertOk()
            ->assertViewHas('scheduleAction', ['url' => route('login'), 'label' => 'Log in for Schedule Extractor'])
            ->assertViewHas('flightPlanAction', ['url' => route('login'), 'label' => 'Log in for Flight Plan Brief'])
            ->assertSee('href="'.route('login').'"', escape: false)
            ->assertSee('href="'.route('register').'"', escape: false)
            ->assertSeeText('Log in for Schedule Extractor')
            ->assertSeeText('Log in for Flight Plan Brief')
            ->assertDontSee('href="'.route('parse.index').'"', escape: false)
            ->assertDontSee('href="'.route('flight-release.index').'"', escape: false);
    }

    #[DataProvider('accountAccess')]
    public function test_authenticated_actions_follow_each_tool_entitlement(
        bool $scheduleForAll,
        bool $flightPlanForAll,
        bool $admin,
        bool $scheduleAccess,
        bool $flightPlanAccess,
    ): void {
        config()->set([
            'features.schedule_extractor.for_all_users' => $scheduleForAll,
            'features.flight_release.for_all_users' => $flightPlanForAll,
        ]);
        $user = $admin ? User::factory()->admin()->create() : User::factory()->create();

        $response = $this->actingAs($user)->get(route('welcome'))->assertOk();

        foreach ([
            ['scheduleAction', $scheduleAccess, 'parse.index', 'Schedule Extractor'],
            ['flightPlanAction', $flightPlanAccess, 'flight-release.index', 'Flight Plan Brief'],
        ] as [$key, $hasAccess, $route, $name]) {
            $response->assertViewHas($key, [
                'url' => $hasAccess ? route($route) : null,
                'label' => $hasAccess ? "Open {$name}" : 'Not available for your account',
            ]);

            if ($hasAccess) {
                $response->assertSee('href="'.route($route).'"', escape: false)
                    ->assertSeeText("Open {$name}");
            } else {
                $response->assertDontSee('href="'.route($route).'"', escape: false)
                    ->assertDontSeeText("Open {$name}");
            }
        }

        $response->assertSee('href="'.route('dashboard').'"', escape: false)
            ->assertDontSeeText('Log in for')
            ->assertDontSeeText('Register');
    }

    /** @return array<string, array{bool, bool, bool, bool, bool}> */
    public static function accountAccess(): array
    {
        return [
            'schedule only' => [true, false, false, true, false],
            'flight plan only' => [false, true, false, false, true],
            'both tools' => [true, true, false, true, true],
            'neither tool' => [false, false, false, false, false],
            'admin with restricted features' => [false, false, true, true, true],
        ];
    }

    #[DataProvider('disabledFeatures')]
    public function test_disabled_tools_keep_their_summary_without_an_action(bool $scheduleEnabled, bool $flightPlanEnabled, bool $admin): void
    {
        config()->set([
            'features.schedule_extractor.enabled' => $scheduleEnabled,
            'features.flight_release.enabled' => $flightPlanEnabled,
        ]);

        if ($admin) {
            $this->actingAs(User::factory()->admin()->create());
        }

        $response = $this->get(route('welcome'))->assertOk()
            ->assertSeeText('Schedule Extractor')
            ->assertSeeText('Flight Plan Extractor')
            ->assertSeeText('Demo')
            ->assertSeeText('Temporarily unavailable');

        foreach ([
            ['scheduleAction', $scheduleEnabled, 'parse.index'],
            ['flightPlanAction', $flightPlanEnabled, 'flight-release.index'],
        ] as [$key, $enabled, $route]) {
            if (! $enabled) {
                $response->assertViewHas($key, ['url' => null, 'label' => 'Temporarily unavailable'])
                    ->assertDontSee('href="'.route($route).'"', escape: false);
            } else {
                $response->assertSee('href="'.route($admin ? $route : 'login').'"', escape: false);
            }
        }
    }

    /** @return array<string, array{bool, bool, bool}> */
    public static function disabledFeatures(): array
    {
        return [
            'guest schedule disabled' => [false, true, false],
            'guest flight plan disabled' => [true, false, false],
            'guest both disabled' => [false, false, false],
            'admin schedule disabled' => [false, true, true],
            'admin flight plan disabled' => [true, false, true],
            'admin both disabled' => [false, false, true],
        ];
    }

    public function test_unverified_accounts_are_offered_email_verification_before_using_tools(): void
    {
        $this->actingAs(User::factory()->unverified()->create())
            ->get(route('welcome'))
            ->assertOk()
            ->assertViewHas('scheduleAction', ['url' => route('verification.notice'), 'label' => 'Verify email for Schedule Extractor'])
            ->assertViewHas('flightPlanAction', ['url' => route('verification.notice'), 'label' => 'Verify email for Flight Plan Brief'])
            ->assertSeeText('Verify email for Schedule Extractor')
            ->assertSeeText('Verify email for Flight Plan Brief')
            ->assertSee('href="'.route('verification.notice').'"', escape: false)
            ->assertDontSeeText('Open Schedule Extractor')
            ->assertDontSeeText('Open Flight Plan Brief');
    }

    public function test_welcome_preserves_theme_controls_accessible_landmarks_and_public_information(): void
    {
        $this->get(route('welcome'))
            ->assertOk()
            ->assertSee('href="#main-content"', escape: false)
            ->assertSee('<main id="main-content" tabindex="-1"', escape: false)
            ->assertSee('aria-label="Main navigation"', escape: false)
            ->assertSee('aria-label="Footer navigation"', escape: false)
            ->assertSee('data-theme-initializer', escape: false)
            ->assertSee('id="welcome-theme-selector"', escape: false)
            ->assertSee('aria-label="Color theme"', escape: false)
            ->assertSee('value="light"', escape: false)
            ->assertSee('value="dark"', escape: false)
            ->assertSee('value="system"', escape: false)
            ->assertSee('alt="Schedule Extractor on mobile with an upload area for roster screenshots or a trip PDF"', escape: false)
            ->assertSeeText('Data Security & Privacy')
            ->assertSeeText('Your account helps protect document access; sign-in rate limits help prevent automated abuse.')
            ->assertSeeText('Uploaded documents are stored privately, outside the public file directory.')
            ->assertDontSeeText('Why do I need an account?')
            ->assertDontSeeText('end-to-end')
            ->assertDontSeeText('auto-deletion')
            ->assertSeeText('Use a unique password.')
            ->assertSeeText('Tool availability depends on your account')
            ->assertSeeText('This independent tool is not affiliated with or endorsed by Jeppesen, Boeing, or other corporate entity.')
            ->assertSee('href="mailto:crewcompasscc@gmail.com"', escape: false)
            ->assertSee('href="'.route('privacy.policy').'"', escape: false)
            ->assertSeeText('Feedback & Bugs')
            ->assertSeeText('Privacy Policy');
    }

    public function test_available_cards_have_one_native_link_covering_each_card(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('welcome'))
            ->assertOk();

        $html = $response->getContent();
        $this->assertIsString($html);
        preg_match_all('/<article\b.*?<\/article>/s', $html, $cards);
        $this->assertCount(2, $cards[0]);

        foreach ($cards[0] as $card) {
            $this->assertSame(1, preg_match_all('/<a\b/', $card));
            $this->assertStringContainsString('after:absolute after:inset-0', $card);
            $this->assertStringContainsString('focus-within:ring-2', $card);
            $this->assertStringContainsString('aria-hidden="true"', $card);
            $this->assertStringNotContainsString('onclick', $card);
        }

        $this->assertStringContainsString('cc-btn-primary', $cards[0][0]);
        $this->assertStringNotContainsString('cc-btn-secondary', $cards[0][0]);
        $this->assertStringContainsString('cc-btn-secondary', $cards[0][1]);
        $this->assertStringNotContainsString('cc-btn-primary', $cards[0][1]);
        $this->assertStringContainsString('Demo · Preview', $cards[0][1]);
        $response->assertSeeInOrder(['Data Security &amp; Privacy', 'Inside Schedule Extractor'], escape: false);
    }

    public function test_unavailable_cards_have_no_click_target_or_keyboard_stop(): void
    {
        config()->set([
            'features.schedule_extractor.enabled' => false,
            'features.flight_release.enabled' => false,
        ]);

        $html = $this->get(route('welcome'))->assertOk()->getContent();
        $this->assertIsString($html);
        preg_match_all('/<article\b.*?<\/article>/s', $html, $cards);
        $this->assertCount(2, $cards[0]);

        foreach ($cards[0] as $card) {
            $this->assertStringContainsString('Temporarily unavailable', $card);
            $this->assertStringNotContainsString('<a ', $card);
            $this->assertStringNotContainsString('after:absolute', $card);
            $this->assertStringNotContainsString('tabindex', $card);
        }
    }
}
