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
        $response->assertSeeText('Jeppesen Crew Access');
        $response->assertSeeText('Schedule Extractor');
        $response->assertSeeInOrder([
            'Jeppesen Crew Access',
            'Schedule Extractor',
        ]);
        $response->assertSeeText('Extract your JCA schedule instantly');
        $response->assertDontSeeText('JCA SCHEDULE PARSER');
    }
}
