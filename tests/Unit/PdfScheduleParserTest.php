<?php

use PHPUnit\Framework\TestCase;
use App\Services\PdfScheduleParser;

class PdfScheduleParserTest extends TestCase
{
    public function test_parse_row_simple()
    {
        $parser = new PdfScheduleParser();
        $line = 'Fri DH G4368 AUS-CVG 17:44 21:17 22:44 01:17 -';
        $res = $parser->parseRowLine($line);

        $this->assertEquals('Fri', $res['day']);
        $this->assertEquals('G4368', $res['flight']);
        $this->assertEquals('AUS-CVG', $res['route']);
        $this->assertContains('17:44', $res['times']);
        $this->assertContains('21:17', $res['times']);
        $this->assertEquals('-', $res['cnx']);
    }

    public function test_parse_row_with_ac()
    {
        $parser = new PdfScheduleParser();
        $line = 'Sat 206 CVG-NRT 05:35 08:25 09:35 23:25 13:50 77X';
        $res = $parser->parseRowLine($line);

        $this->assertEquals('206', $res['flight']);
        $this->assertEquals('CVG-NRT', $res['route']);
        $this->assertContains('05:35', $res['times']);
        $this->assertEquals('77X', $res['ac']);
    }

    public function test_parse_row_with_block_and_ac()
    {
        $parser = new PdfScheduleParser();
        $line = 'Fri 704 HKG-DWC 20:45 01:15 12:45 21:15 08:30 77V';
        $res = $parser->parseRowLine($line);

        $this->assertEquals('704', $res['flight']);
        $this->assertEquals('HKG-DWC', $res['route']);
        $this->assertContains('20:45', $res['times']);
        $this->assertEquals('77V', $res['ac']);
        $this->assertEquals('08:30', $res['block']);
    }
}
