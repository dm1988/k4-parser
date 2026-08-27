<?php

namespace Tests\Unit;

use App\Services\FlightPlan\Extractor\WeatherExtractor;
use PHPUnit\Framework\TestCase;

class WeatherExtractorTest extends TestCase
{
    public function test_it_extracts_raw_reports_by_operational_airport_role_and_release_raim_status(): void
    {
        $result = (new WeatherExtractor)->extract($this->fixture());

        $this->assertSame('KAAA', $result['data']['departure']['airport']);
        $this->assertCount(2, $result['data']['departure']['metars']);
        $this->assertCount(1, $result['data']['departure']['tafs']);
        $this->assertStringStartsWith('METAR KAAA 250553Z', $result['data']['departure']['metars'][0]);
        $this->assertStringStartsWith('SPECI KAAA 250520Z', $result['data']['departure']['metars'][1]);
        $this->assertSame(
            'TAF AMD KAAA 250521Z 2506/2612 28006KT P6SM VCSH BKN070 FM250800 21005KT P6SM SCT080',
            $result['data']['departure']['tafs'][0],
        );
        $this->assertSame('KBBB', $result['data']['destination']['airport']);
        $this->assertCount(1, $result['data']['destination']['metars']);
        $this->assertCount(1, $result['data']['destination']['tafs']);
        $this->assertSame('KCCC', $result['data']['alternate']['airport']);
        $this->assertCount(1, $result['data']['alternate']['metars']);
        $this->assertCount(1, $result['data']['alternate']['tafs']);
        $this->assertSame(
            'PASSED RAIM REQUIREMENTS FOR PRIMARY NAVIGATION VALID FROM 1020Z TO 1240Z',
            $result['data']['raim'],
        );
        $this->assertSame([
            'weather_departure',
            'weather_destination',
            'weather_alternate',
            'weather_raim',
        ], array_keys($result['source_fragments']));
    }

    public function test_it_supports_flattened_pdf_text_and_keeps_missing_roles_explicit(): void
    {
        $fixture = $this->fixture();
        $departureOnly = substr($fixture, 0, strpos($fixture, 'ARRIVAL:'))
            .'KALITTA BRIEF PAGE 16 OF 100';
        $data = (new WeatherExtractor)->extract(str_replace("\n", '', $departureOnly))['data'];

        $this->assertSame('KAAA', $data['departure']['airport']);
        $this->assertCount(2, $data['departure']['metars']);
        $this->assertCount(1, $data['departure']['tafs']);
        $this->assertNull($data['destination']);
        $this->assertNull($data['alternate']);
    }

    public function test_it_does_not_infer_reports_from_unconfirmed_weather_or_raim_text(): void
    {
        $result = (new WeatherExtractor)->extract(
            'WEATHER CONDITIONS VFR. PASSED RAIM REQUIREMENTS. ROUTE KAAA DCT KBBB',
        );

        $this->assertSame([
            'departure' => null,
            'destination' => null,
            'alternate' => null,
            'raim' => null,
        ], $result['data']);
        $this->assertSame([], $result['source_fragments']);
    }

    private function fixture(): string
    {
        $contents = file_get_contents(__DIR__.'/../Fixtures/FlightPlan/weather/departure-destination-alternate.txt');

        $this->assertIsString($contents);

        return $contents;
    }
}
