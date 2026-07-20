<?php

namespace App\Console\Commands;

use App\Enums\ScheduleDocumentType;
use App\Services\ScheduleFormatParser;
use App\Services\SchedulePdfExtractor;
use Illuminate\Console\Command;
use Throwable;

class ParseSchedule extends Command
{
    protected $signature = 'parse:schedule {file : Path to the PDF file}';

    protected $description = 'Parse a Trip Information PDF and output JSON';

    public function handle(SchedulePdfExtractor $extractor, ScheduleFormatParser $parser): int
    {
        $file = $this->argument('file');

        if (! file_exists($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        try {
            $data = $extractor->extract($file);
            $data['parsed'] = $parser->parse(
                $data['text'],
                ScheduleDocumentType::TripInformation->value,
            );

            try {
                $flightDtos = $parser->extractFlightsDto(
                    $data['text'],
                    ScheduleDocumentType::TripInformation->value,
                );
                $data['parsed']['flight_dtos'] = array_map(
                    fn ($flight): array => $flight->toArray(),
                    $flightDtos,
                );
            } catch (Throwable) {
            }

            $this->line((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Parse error: '.$exception->getMessage());

            return 2;
        }
    }
}
