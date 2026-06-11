<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PdfScheduleParser;

class ParseSchedule extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'parse:schedule {file : Path to the PDF file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Parse a schedule PDF and output JSON';

    public function handle(PdfScheduleParser $parser)
    {
        $file = $this->argument('file');

        if (!file_exists($file)) {
            $this->error("File not found: $file");
            return 1;
        }

        try {
            $data = $parser->parse($file);
            $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return 0;
        } catch (\Exception $e) {
            $this->error('Parse error: ' . $e->getMessage());
            return 2;
        }
    }
}
