<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExtractScheduleCommandTest extends TestCase
{
    public function test_it_fails_when_the_schedule_file_does_not_exist(): void
    {
        $missingPath = storage_path('app/missing-schedule.pdf');

        $this->artisan('parse:schedule', ['file' => $missingPath])
            ->expectsOutput("File not found: {$missingPath}")
            ->assertFailed();
    }
}
