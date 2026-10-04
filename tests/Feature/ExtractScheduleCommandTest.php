<?php

namespace Tests\Feature;

use App\Services\Schedule\Extractor\PdfTextExtractor;
use App\Services\Schedule\Extractor\ScheduleFormatParser;
use Illuminate\Support\Facades\Exceptions;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class ExtractScheduleCommandTest extends TestCase
{
    public function test_optional_dto_failure_is_reported_and_warns_without_losing_parsed_output(): void
    {
        Exceptions::fake();
        $exception = new RuntimeException('DTO extraction failed');
        $path = tempnam('/tmp', 'schedule-command-');
        $this->assertIsString($path);

        $this->mock(PdfTextExtractor::class, function (MockInterface $mock) use ($path): void {
            $mock->shouldReceive('extract')->once()->with($path)->andReturn(['text' => 'schedule']);
        });
        $this->mock(ScheduleFormatParser::class, function (MockInterface $mock) use ($exception): void {
            $mock->shouldReceive('parse')->once()->andReturn(['calendar_events' => []]);
            $mock->shouldReceive('extractFlightsDto')->once()->andThrow($exception);
        });

        try {
            $this->artisan('parse:schedule', ['file' => $path])
                ->expectsOutput('Flight DTO export failed; parsed schedule data is still available.')
                ->expectsOutputToContain('"calendar_events": []')
                ->assertSuccessful();

            Exceptions::assertReported(fn (RuntimeException $reported): bool => $reported === $exception);
        } finally {
            unlink($path);
        }
    }

    public function test_it_fails_when_the_schedule_file_does_not_exist(): void
    {
        $missingPath = storage_path('app/missing-schedule.pdf');

        $this->artisan('parse:schedule', ['file' => $missingPath])
            ->expectsOutput("File not found: {$missingPath}")
            ->assertFailed();
    }
}
