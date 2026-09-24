<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class EmployeeCardComponentTest extends TestCase
{
    public function test_it_renders_confirmed_employee_details_and_high_minimums_status(): void
    {
        $html = Blade::render('<x-flight-release.employee-card :member="$member" />', [
            'member' => [
                'name' => 'GONZALEZ D',
                'role' => 'SIC/FO',
                'roleBadgeLabel' => 'SIC',
                'roleBadgeColor' => 'bg-blue-600 dark:bg-blue-500/20 dark:text-blue-400',
                'details' => 'YIP',
                'employeeNumber' => '72914',
                'highMins' => true,
            ],
        ]);

        $this->assertStringContainsString('data-employee-card', $html);
        $this->assertStringContainsString('GONZALEZ D', $html);
        $this->assertStringContainsString('aria-label="Crew role SIC/FO"', $html);
        $this->assertStringContainsString('bg-blue-600 dark:bg-blue-500/20 dark:text-blue-400', $html);
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
                'roleBadgeColor' => 'bg-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400',
                'details' => null,
                'employeeNumber' => null,
                'highMins' => false,
            ],
        ]);

        $this->assertStringContainsString('MORGAN A', $html);
        $this->assertStringContainsString('PIC', $html);
        $this->assertStringContainsString('bg-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400', $html);
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
                    'roleBadgeColor' => 'bg-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400',
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
