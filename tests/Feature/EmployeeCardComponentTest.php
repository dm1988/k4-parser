<?php

namespace Tests\Feature;

use App\Enums\CrewPosition;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class EmployeeCardComponentTest extends TestCase
{
    public function test_all_roles_unknown_and_missing_roles_keep_visible_and_accessible_identity(): void
    {
        $roles = [...CrewPosition::values(), 'UNKNOWN-LONG-ROLE', null];

        foreach ($roles as $role) {
            $position = CrewPosition::tryFrom($role ?? '');
            $label = $position?->badgeLabel() ?? $role ?? '—';
            $palette = $position?->badgeColor() ?? CrewPosition::defaultBadgeColor();
            $html = Blade::render('<x-flight-release.employee-card :member="$member" />', [
                'member' => [
                    'name' => 'VERYLONGUNBROKENCREWMEMBERNAME',
                    'role' => $role,
                    'roleBadgeLabel' => $label,
                    'roleBadgeColor' => $palette,
                    'details' => 'VERYLONGUNBROKENBASEDETAILS',
                    'employeeNumber' => '123456789012345678901234567890',
                    'highMins' => true,
                ],
            ]);

            $this->assertStringContainsString('role="img"', $html);
            $this->assertStringContainsString('aria-label="'.($role ? 'Crew role '.$role : 'Crew role not confirmed').'"', $html);
            $this->assertMatchesRegularExpression('/>\s*'.preg_quote($label, '/').'\s*</', $html);
            $this->assertStringContainsString($palette, $html);
            $this->assertStringContainsString('min-h-12 w-12 shrink-0', $html);
            $this->assertStringContainsString('[overflow-wrap:anywhere]', $html);
            $this->assertStringContainsString('w-fit max-w-full', $html);
            $this->assertStringContainsString('<span class="min-w-0 [overflow-wrap:anywhere]">High mins</span>', $html);
            $this->assertStringNotContainsString('truncate', $html);
            $this->assertStringNotContainsString('overflow-hidden', $html);
            $this->assertMatchesRegularExpression('/Crew role.*VERYLONGUNBROKENCREWMEMBERNAME.*Employee number.*123456789012345678901234567890.*VERYLONGUNBROKENBASEDETAILS.*High mins/s', $html);
        }
    }

    public function test_it_renders_confirmed_employee_details_and_high_minimums_status(): void
    {
        $html = Blade::render('<x-flight-release.employee-card :member="$member" />', [
            'member' => [
                'name' => 'GONZALEZ D',
                'role' => 'SIC/FO',
                'roleBadgeLabel' => 'SIC',
                'roleBadgeColor' => 'bg-blue-600 text-white dark:bg-blue-500/20 dark:text-blue-400',
                'details' => 'YIP',
                'employeeNumber' => '72914',
                'highMins' => true,
            ],
        ]);

        $this->assertStringContainsString('data-employee-card', $html);
        $this->assertStringContainsString('GONZALEZ D', $html);
        $this->assertStringContainsString('aria-label="Crew role SIC/FO"', $html);
        $this->assertStringContainsString('bg-blue-600 text-white dark:bg-blue-500/20 dark:text-blue-400', $html);
        $this->assertMatchesRegularExpression('/>\s*SIC\s*</', $html);
        $this->assertStringContainsString('YIP', $html);
        $this->assertStringContainsString('Employee number', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertStringContainsString('72914', $html);
        $this->assertStringContainsString('High mins', $html);
        $this->assertStringContainsString('flex min-w-0 items-center gap-3', $html);
        $this->assertStringContainsString('text-base font-extrabold', $html);
    }

    public function test_it_labels_an_unconfirmed_employee_number(): void
    {
        $html = Blade::render('<x-flight-release.employee-card :member="$member" />', [
            'member' => [
                'name' => 'MORGAN A',
                'role' => 'PIC',
                'roleBadgeLabel' => 'PIC',
                'roleBadgeColor' => 'bg-emerald-700 text-white dark:bg-emerald-500/20 dark:text-emerald-400',
                'details' => null,
                'employeeNumber' => null,
                'highMins' => false,
            ],
        ]);

        $this->assertStringContainsString('MORGAN A', $html);
        $this->assertStringContainsString('PIC', $html);
        $this->assertStringContainsString('bg-emerald-700 text-white dark:bg-emerald-500/20 dark:text-emerald-400', $html);
        $this->assertStringContainsString('Not confirmed', $html);
        $this->assertStringNotContainsString('High mins', $html);
    }

    public function test_it_can_hide_the_employee_number(): void
    {
        $html = Blade::render(
            '<x-flight-release.employee-card :member="$member" :show-employee-number="false" />',
            [
                'member' => [
                    'name' => 'MORGAN A',
                    'role' => 'PIC',
                    'roleBadgeLabel' => 'PIC',
                    'roleBadgeColor' => 'bg-emerald-700 text-white dark:bg-emerald-500/20 dark:text-emerald-400',
                    'details' => null,
                    'employeeNumber' => '4387',
                    'highMins' => false,
                ],
            ],
        );

        $this->assertStringContainsString('MORGAN A', $html);
        $this->assertStringContainsString('PIC', $html);
        $this->assertStringNotContainsString('Employee number', $html);
        $this->assertStringNotContainsString('4387', $html);
    }
}
