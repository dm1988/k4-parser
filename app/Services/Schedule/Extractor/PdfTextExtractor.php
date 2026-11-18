<?php

namespace App\Services\Schedule\Extractor;

use Smalot\PdfParser\Parser;
use Symfony\Component\Process\Process;

class PdfTextExtractor
{
    private readonly Parser $parser;

    public function __construct()
    {
        $this->parser = new Parser;
    }

    /**
     * @return array{
     *     file: string,
     *     text: string,
     *     pdf_meta: array{page_count: int}
     * }
     */
    public function extract(string $path): array
    {
        $pdf = $this->parser->parseFile($path);

        return [
            'file' => $path,
            'text' => str_replace("\x00", '', $pdf->getText()),
            'pdf_meta' => [
                'page_count' => count($pdf->getPages()),
            ],
        ];
    }

    public function extractLayout(string $path): string
    {
        $process = new Process(['pdftotext', '-layout', '-enc', 'UTF-8', $path, '-']);
        $process->setTimeout(30);
        $process->mustRun();

        return str_replace("\x00", '', $process->getOutput());
    }
}
