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
        $response->assertSeeText('Crew Compass');
        $response->assertSeeText('K4 Extractor');
        $response->assertSeeText('Schedule Extractor');
        $response->assertSeeInOrder([
            'K4 Extractor',
            'Schedule Extractor',
            'Flight Plan Extractor',
        ]);
        $response->assertSeeText('Flight Plan Brief');
        $response->assertSeeText('This independent tool is not affiliated with or endorsed by Jeppesen, Boeing, or other corporate entity.');
        $response->assertDontSeeText('JCA SCHEDULE PARSER');
    }
}
