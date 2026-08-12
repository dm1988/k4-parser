<?php

namespace Tests\Feature;

use Illuminate\Foundation\Vite;
use Tests\TestCase;

class ViteDisabledTest extends TestCase
{
    public function test_vite_assets_are_not_required_by_backend_tests(): void
    {
        $this->assertSame('', (string) app(Vite::class)(['resources/css/app.css']));
    }
}
