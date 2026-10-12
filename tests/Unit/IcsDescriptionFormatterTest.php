<?php

namespace Tests\Unit;

use App\Services\Calendar\IcsDescriptionFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IcsDescriptionFormatterTest extends TestCase
{
    public function test_it_formats_compact_flight_notes_and_omits_airport_enrichment(): void
    {
        $description = app(IcsDescriptionFormatter::class)->format([
            'type' => 'flight',
            'start' => '2026-10-07T16:00:00Z',
            'end' => '2026-10-07T20:00:00Z',
            'metadata' => [
                'flight_number' => 'CKS 256', 'tail_number' => 'N774CK', 'aircraft' => '77X', 'position' => 'AFO',
                'origin' => 'ICN', 'origin_iata' => 'ICN', 'origin_icao' => 'RKSI', 'origin_name' => 'Incheon International Airport',
                'origin_city' => 'Seoul', 'origin_state' => '28', 'origin_country' => 'KR', 'origin_airport_status' => 'found',
                'destination' => 'HKG', 'destination_iata' => 'HKG', 'destination_icao' => 'VHHH',
                'destination_name' => 'Hong Kong International Airport', 'destination_city' => 'Hong Kong',
                'destination_state' => 'NT', 'destination_country' => 'HK', 'destination_airport_status' => 'found',
                'block_time' => '4:00h', 'leg_local_start' => 'Oct 08 01:00', 'leg_local_end' => 'Oct 08 04:00',
                'duty_local_start' => 'Oct 07 23:00', 'duty_local_end' => 'Oct 08 04:30',
                'crew_count' => 2, 'operating_crew_count' => 1, 'deadheading_crew_count' => 1,
                'crew' => [
                    ['name' => 'Jane Doe', 'role' => 'FO', 'base' => 'PVD', 'employee_id' => '70978', 'deadheading' => false],
                    ['name' => 'John Smith', 'role' => 'DH', 'employee_id' => '72446', 'deadheading' => true],
                ],
                'raw_lines' => ['Private source text'], 'flightaware_url' => 'https://example.test/flight',
            ],
        ]);

        $this->assertSame(<<<'TEXT'
✈️ FLIGHT DETAILS
Flight: CKS 256  |  Tail: N774CK (77X)  |  Pos: AFO
Route: ICN (RKSI) ➔ HKG (VHHH)

⏰ TIMES
UTC:   Oct 07 16:00z ➔ 20:00z  (Block: 4:00h)
Local: Oct 08 01:00 ➔ 04:00

👨‍✈️ DUTY TIMES
Local: Oct 07 23:00 ➔ Oct 08 04:30

👥 CREW (1 Ops / 1 DH)
• Jane Doe   │ FO │ PVD │ #70978
• John Smith │ DH │ #72446
TEXT, $description);
    }

    #[DataProvider('utcRanges')]
    public function test_it_labels_utc_and_keeps_dates_when_the_range_crosses_a_day_or_year(string $start, string $end, string $expected): void
    {
        $description = app(IcsDescriptionFormatter::class)->format(['type' => 'flight', 'start' => $start, 'end' => $end]);

        $this->assertStringContainsString($expected, $description);
        $this->assertStringNotContainsString('Local:', $description);
        $this->assertStringNotContainsString('Block:', $description);
        $this->assertStringNotContainsString('CREW', $description);
    }

    public static function utcRanges(): array
    {
        return [
            ['2026-10-07T12:00:00-04:00', '2026-10-07T16:00:00-04:00', 'UTC:   Oct 07 16:00z ➔ 20:00z'],
            ['2026-10-07T23:00:00Z', '2026-10-08T02:00:00Z', 'UTC:   Oct 07 23:00z ➔ Oct 08 02:00z'],
            ['2026-12-31T23:00:00Z', '2027-01-01T02:00:00Z', 'UTC:   Dec 31 2026 23:00z ➔ Jan 01 2027 02:00z'],
        ];
    }

    public function test_it_does_not_invent_missing_route_times_aircraft_or_crew_counts(): void
    {
        $description = app(IcsDescriptionFormatter::class)->format([
            'type' => 'flight', 'end' => '2026-10-08T02:00:00Z',
            'metadata' => ['origin' => 'LAX', 'leg_local_end' => 'Oct 08 01:00', 'operating_crew_count' => 2],
        ]);

        $this->assertStringContainsString('Route: LAX ➔ —', $description);
        $this->assertStringContainsString('UTC:   — ➔ Oct 08 02:00z', $description);
        $this->assertStringContainsString('Local: — ➔ Oct 08 01:00', $description);
        $this->assertStringContainsString('👥 CREW (2 Ops / — DH)', $description);
        $this->assertStringNotContainsString('Tail:', $description);
        $this->assertStringNotContainsString('Aircraft:', $description);
        $this->assertStringNotContainsString('DUTY TIMES', $description);
    }

    public function test_it_preserves_explicit_zero_crew_counts_and_aircraft_without_a_tail(): void
    {
        $description = app(IcsDescriptionFormatter::class)->format([
            'type' => 'flight', 'metadata' => ['aircraft' => '77X', 'operating_crew_count' => 0, 'deadheading_crew_count' => 0],
        ]);

        $this->assertStringContainsString('Aircraft: 77X', $description);
        $this->assertStringContainsString('👥 CREW (0 Ops / 0 DH)', $description);
        $this->assertStringNotContainsString('TIMES', $description);
    }

    public function test_it_keeps_custom_notes_and_deadhead_details_without_duplicate_dh_markers(): void
    {
        $description = app(IcsDescriptionFormatter::class)->format([
            'type' => 'duty',
            'metadata' => [
                'duty_type' => 'deadhead', 'origin' => 'LAX', 'destination' => 'AUS', 'flight_number' => 'UA 123',
                'notes' => "Gate A\nBring passport", 'airline_name' => 'United Airlines',
                'crew' => [['name' => 'Jane Doe', 'role' => 'DH', 'employee_id' => '12345', 'deadheading' => true]],
            ],
        ]);

        $this->assertStringContainsString('Flight: UA 123', $description);
        $this->assertStringContainsString('United Airlines', $description);
        $this->assertStringContainsString("Gate A\nBring passport", $description);
        $this->assertStringContainsString('• Jane Doe │ DH │ #12345', $description);
        $this->assertStringNotContainsString('Deadhead: Yes', $description);
    }

    public function test_unicode_names_and_missing_roles_are_retained(): void
    {
        $description = app(IcsDescriptionFormatter::class)->format([
            'type' => 'flight', 'metadata' => ['crew' => [
                ['name' => 'José', 'role' => 'FO', 'employee_id' => '00123', 'deadheading' => false],
                ['name' => 'Alina', 'employee_id' => '00789'],
            ]],
        ]);

        $this->assertStringContainsString('• José  │ FO │ #00123', $description);
        $this->assertStringContainsString('• Alina │ #00789', $description);
        $this->assertStringContainsString('👥 CREW (2 total)', $description);
        $this->assertTrue(mb_check_encoding($description, 'UTF-8'));
    }

    public function test_operational_notes_and_labeled_slot_times_remain_available(): void
    {
        $description = app(IcsDescriptionFormatter::class)->format([
            'type' => 'flight', 'metadata' => [
                'slot_times_utc' => ['Oct 08 10:00z'], 'slot_times_local' => ['Oct 08 18:00'],
                'notes' => 'Bring paperwork', 'origin_name' => 'Airport enrichment',
            ],
        ]);

        $this->assertStringContainsString('Slot times UTC: Oct 08 10:00z', $description);
        $this->assertStringContainsString('Slot times local: Oct 08 18:00', $description);
        $this->assertStringContainsString('Notes: Bring paperwork', $description);
        $this->assertStringNotContainsString('Airport enrichment', $description);
    }

    public function test_invalid_or_missing_utc_dates_do_not_create_current_time_values(): void
    {
        $description = app(IcsDescriptionFormatter::class)->format([
            'type' => 'flight', 'start' => 'invalid date', 'end' => '', 'metadata' => ['block_time' => '2:00h'],
        ]);

        $this->assertSame("⏰ TIMES\nBlock: 2:00h", $description);
    }

    public function test_non_flight_events_keep_their_existing_notes(): void
    {
        $description = app(IcsDescriptionFormatter::class)->format([
            'type' => 'layover', 'metadata' => ['station' => 'LAX', 'hotel' => 'Airport Hotel'],
        ]);

        $this->assertStringContainsString('• Station: LAX', $description);
        $this->assertStringContainsString('• Hotel: Airport Hotel', $description);
    }
}
