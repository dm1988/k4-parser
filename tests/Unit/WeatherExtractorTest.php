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
            "TAF AMD KAAA 250521Z 2506/2612 28006KT P6SM VCSH BKN070\nFM250800 21005KT P6SM SCT080",
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

    public function test_it_formats_flattened_taf_continuations_and_keeps_missing_roles_explicit(): void
    {
        $fixture = $this->fixture();
        $departureOnly = substr($fixture, 0, strpos($fixture, 'ARRIVAL:'))
            .'KALITTA BRIEF PAGE 16 OF 100';
        $data = (new WeatherExtractor)->extract(str_replace("\n", '', $departureOnly))['data'];

        $this->assertSame('KAAA', $data['departure']['airport']);
        $this->assertCount(2, $data['departure']['metars']);
        $this->assertCount(1, $data['departure']['tafs']);
        $this->assertSame(
            "TAF AMD KAAA 250521Z 2506/2612 28006KT P6SM VCSH BKN070\nFM250800 21005KT P6SM SCT080",
            $data['departure']['tafs'][0],
        );
        $this->assertNull($data['destination']);
        $this->assertNull($data['alternate']);
    }

    public function test_it_preserves_source_lines_and_normalizes_equivalent_flattened_reports(): void
    {
        $multilineReport = implode("\r\n", [
            'DEPARTURE: TEST AIRPORT',
            'TAF KAAA 250521Z 2506/2612   28006KT P6SM BKN070',
            '  FM250800   21005KT P6SM SCT080',
            '  TEMPO 2510/2514 3SM -RA BKN030 ?',
            'TAF KAAA 250521Z 2506/2612 28006KT P6SM BKN070',
            'FM250800 21005KT P6SM SCT080',
            'TEMPO 2510/2514 3SM -RA BKN030',
            'METAR KAAA 250553Z 22006KT 10SM FEW060 ?',
            'KALITTA BRIEF PAGE 16 OF 100',
        ]);

        $data = (new WeatherExtractor)->extract($multilineReport)['data']['departure'];

        $this->assertNotNull($data);
        $this->assertSame([
            "TAF KAAA 250521Z 2506/2612 28006KT P6SM BKN070\nFM250800 21005KT P6SM SCT080\nTEMPO 2510/2514 3SM -RA BKN030",
        ], $data['tafs']);
        $this->assertSame([
            'METAR KAAA 250553Z 22006KT 10SM FEW060',
        ], $data['metars']);

        $flattened = str_replace(["\r", "\n"], '', $multilineReport);
        $flattenedData = (new WeatherExtractor)->extract($flattened)['data']['departure'];

        $this->assertNotNull($flattenedData);
        $this->assertSame($data['tafs'], $flattenedData['tafs']);
    }

    public function test_it_formats_recognized_change_groups_in_flattened_tafs_only(): void
    {
        $text = 'DEPARTURE: TEST AIRPORT '
            .'TAF KCVG 252320Z 2600/2706 VRB03KT P6SM SCT250 FM261500 22006KT P6SM SCT040 FM270000 20004KT P6SM SCT100 BKN250 '
            .'TAF KCVG 252123Z 2521/2624 23004KT P6SM FEW050 FM260000 VRB03KT P6SM SCT250 FM261500 22006KT P6SM SCT040 '
            .'METAR KCVG 260252Z 00000KT 10SM SCT250 RMK BECMG WEATHER '
            .'KALITTA BRIEF PAGE 16 OF 100';

        $data = (new WeatherExtractor)->extract($text)['data']['departure'];

        $this->assertNotNull($data);
        $this->assertSame([
            "TAF KCVG 252320Z 2600/2706 VRB03KT P6SM SCT250\nFM261500 22006KT P6SM SCT040\nFM270000 20004KT P6SM SCT100 BKN250",
            "TAF KCVG 252123Z 2521/2624 23004KT P6SM FEW050\nFM260000 VRB03KT P6SM SCT250\nFM261500 22006KT P6SM SCT040",
        ], $data['tafs']);
        $this->assertSame([
            'METAR KCVG 260252Z 00000KT 10SM SCT250 RMK BECMG WEATHER',
        ], $data['metars']);
    }

    public function test_it_keeps_combined_probability_groups_on_one_taf_line(): void
    {
        $text = 'DEPARTURE: TEST AIRPORT '
            .'TAF KAAA 252300Z 2600/2624 06007KT CAVOK BECMG 2608/2609 09012KT '
            .'PROB30 TEMPO 2611/2615 09015G25KT BECMG 2617/2619 08007KT '
            .'KALITTA BRIEF PAGE 16 OF 100';

        $data = (new WeatherExtractor)->extract($text)['data']['departure'];

        $this->assertNotNull($data);
        $this->assertSame([
            "TAF KAAA 252300Z 2600/2624 06007KT CAVOK\nBECMG 2608/2609 09012KT\nPROB30 TEMPO 2611/2615 09015G25KT\nBECMG 2617/2619 08007KT",
        ], $data['tafs']);
    }

    public function test_it_extracts_raim_when_flattened_text_is_concatenated_with_the_next_section(): void
    {
        $result = (new WeatherExtractor)->extract(
            'STAB POSITION INDICATORS(O)PASSED RAIM REQUIREMENTS FOR PRIMARY NAVIGATION'
            .'VALID FROM 0715Z TO 0935ZI0001/23 NOTAMN Q) KZID',
        );

        $this->assertSame(
            'PASSED RAIM REQUIREMENTS FOR PRIMARY NAVIGATION VALID FROM 0715Z TO 0935Z',
            $result['data']['raim'],
        );
        $this->assertSame(
            'PASSED RAIM REQUIREMENTS FOR PRIMARY NAVIGATION VALID FROM 0715Z TO 0935Z',
            $result['source_fragments']['weather_raim'],
        );
    }

    public function test_it_rejects_raim_with_an_overlong_utc_time(): void
    {
        $result = (new WeatherExtractor)->extract(
            'PASSED RAIM REQUIREMENTS FOR PRIMARY NAVIGATION VALID FROM 0715Z TO 0935Z1',
        );

        $this->assertNull($result['data']['raim']);
        $this->assertArrayNotHasKey('weather_raim', $result['source_fragments']);
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
