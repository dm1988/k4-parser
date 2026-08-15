<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class DemoBadgeComponentTest extends TestCase
{
    public function test_default_variant_renders_translated_demo_fallback(): void
    {
        $html = Blade::render('<x-demo-badge />');

        $this->assertStringContainsString('Demo', $html);
        $this->assertStringContainsString('bg-amber-100', $html);
        $this->assertStringContainsString('dark:bg-amber-900/40', $html);
        $this->assertStringContainsString('rounded px-2 py-0.5 text-xs font-medium', $html);
    }

    public function test_variants_custom_content_and_attributes_are_rendered(): void
    {
        $infoHtml = Blade::render(
            '<x-demo-badge variant="info" class="tracking-wide">Preview</x-demo-badge>',
        );
        $successHtml = Blade::render(
            '<x-demo-badge variant="success">Available</x-demo-badge>',
        );

        $this->assertStringContainsString('Preview', $infoHtml);
        $this->assertStringContainsString('tracking-wide', $infoHtml);
        $this->assertStringContainsString('bg-blue-100', $infoHtml);
        $this->assertStringContainsString('dark:bg-blue-900/40', $infoHtml);
        $this->assertStringContainsString('Available', $successHtml);
        $this->assertStringContainsString('bg-green-100', $successHtml);
        $this->assertStringContainsString('dark:bg-green-900/40', $successHtml);
    }

    public function test_unknown_variant_falls_back_to_default_styles(): void
    {
        $html = Blade::render('<x-demo-badge variant="unknown" />');

        $this->assertStringContainsString('bg-amber-100', $html);
        $this->assertStringNotContainsString('unknown', $html);
    }
}
