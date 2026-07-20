<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSeeText('JCA SCHEDULE EXTRACTOR');
        $response->assertSeeText('Extract your JCA schedule instantly');
        $response->assertDontSeeText('JCA SCHEDULE PARSER');
    }
}
