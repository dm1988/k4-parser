<?php

namespace Tests\Unit\Services\Schedule;

use App\DTOs\DutyEvent;
use App\DTOs\Flight;
use App\Mappers\FlightMapper;
use App\Services\Schedule\ScheduleResultMerger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ScheduleResultMergerTest extends TestCase
{
    #[DataProvider('nonFlightIdentities')]
    public function test_it_uses_type_specific_identity_for_non_flight_events(array $first, array $second, int $count): void
    {
        $parsed = $this->merge([$first], [$second]);

        $this->assertCount($count, $parsed['calendar_events']);
        $this->assertSame([], $parsed['merge_conflicts']);
    }

    public static function nonFlightIdentities(): array
    {
        $duty = self::event('duty', ['station' => 'LAX', 'duty_type' => 'R1']);
        $layover = self::event('layover', ['station' => 'LAX', 'hotel' => 'Hotel One']);

        return [
            'same duty with different evidence' => [$duty, array_replace_recursive($duty, ['metadata' => ['raw_lines' => ['Another source']]]), 1],
            'different reserve types' => [$duty, array_replace_recursive($duty, ['metadata' => ['duty_type' => 'R2']]), 2],
            'training versus reserve' => [$duty, array_replace_recursive($duty, ['metadata' => ['duty_type' => 'training']]), 2],
            'different duty stations' => [$duty, array_replace_recursive($duty, ['metadata' => ['station' => 'ANC']]), 2],
            'different named assignments' => [$duty, array_replace($duty, ['title' => 'Medical appointment']), 2],
            'same layover with extra details' => [$layover, array_replace_recursive($layover, ['metadata' => ['phone' => '555-0100']]), 1],
            'different hotels' => [$layover, array_replace_recursive($layover, ['metadata' => ['hotel' => 'Hotel Two']]), 2],
            'off versus one in seven' => [self::event('off'), self::event('1in7'), 2],
            'matching off days' => [self::event('off'), self::event('off', ['raw_lines' => ['Another source']]), 1],
            'unknown events are retained' => [self::event('unknown'), self::event('unknown'), 2],
        ];
    }

    public function test_it_matches_dto_and_array_observations_at_equivalent_utc_instants(): void
    {
        $first = self::flight();
        $second = $first;
        $second['start'] = '2026-06-16T08:45:00+09:00';
        $second['end'] = '2026-06-16T12:45:00+09:00';
        $second['metadata']['crew_count'] = 0;

        $parsed = $this->merge([Flight::fromArray($first)], [$second]);

        $this->assertCount(1, $parsed['calendar_events']);
        $event = $parsed['calendar_events'][0];
        $this->assertSame('2026-06-15T23:45:00+00:00', $event['start']);
        $this->assertSame('2026-06-16T03:45:00+00:00', $event['end']);
        $this->assertSame(0, $event['metadata']['crew_count']);
        $this->assertCount(2, $event['metadata']['source_observations']);
        $this->assertSame([], $parsed['merge_conflicts']);
    }

    public function test_it_preserves_repeated_identical_events_within_a_single_source(): void
    {
        $parsed = $this->merge([self::flight(), self::flight()], [self::flight()]);

        $this->assertCount(2, $parsed['calendar_events']);
        $this->assertSame([2, 1], array_map(static fn (array $event): int => count($event['metadata']['source_observations']), $parsed['calendar_events']));
    }

    public function test_it_preserves_flights_with_incomplete_identity_instead_of_guessing(): void
    {
        $event = self::flight();
        unset($event['metadata']['flight_number']);

        $parsed = $this->merge([$event], [$event]);

        $this->assertCount(2, $parsed['calendar_events']);
        $this->assertSame([], $parsed['merge_conflicts']);
    }

    public function test_it_retains_unknown_events_and_distinct_source_references_for_identical_uploads(): void
    {
        $source = ['filename' => 'same.png', 'raw_text' => 'Same OCR'];
        $result = ['calendar_events' => [self::event('unknown')]];

        $parsed = (new ScheduleResultMerger(new FlightMapper))->merge([$result, $result], [$source, $source]);

        $this->assertCount(2, $parsed['calendar_events']);
        $this->assertCount(2, array_unique(array_column($parsed['sources'], 'id')));
        $this->assertSame([1, 2], array_column($parsed['sources'], 'position'));
    }

    public function test_it_does_not_merge_operating_flights_with_deadheads(): void
    {
        $flight = self::flight();
        $deadhead = $flight;
        $deadhead['type'] = 'deadhead';

        $parsed = $this->merge([$flight], [$deadhead]);

        $this->assertCount(2, $parsed['calendar_events']);
        $this->assertSame([], $parsed['merge_conflicts']);
    }

    public function test_it_accepts_non_flight_dtos_without_losing_source_metadata(): void
    {
        $data = self::event('duty', ['station' => 'LAX', 'duty_type' => 'R1']);
        $dto = DutyEvent::fromArray([...$data, 'crewCount' => 4, 'layoverDuration' => '12:00']);

        $parsed = $this->merge([$dto], [$data]);

        $this->assertCount(1, $parsed['calendar_events']);
        $this->assertSame('R1', $parsed['calendar_events'][0]['metadata']['duty_type']);
        $this->assertSame(4, $parsed['calendar_events'][0]['metadata']['crew_count']);
        $this->assertSame('12:00', $parsed['calendar_events'][0]['metadata']['layover_duration']);
        $this->assertCount(2, $parsed['calendar_events'][0]['metadata']['source_observations']);
        $this->assertSame([], $parsed['merge_conflicts']);
    }

    public function test_it_clears_conflicting_optional_values_and_retains_both_observations(): void
    {
        $first = self::flight();
        $second = $first;
        $first['metadata']['tail_number'] = 'N772CK';
        $second['metadata']['tail_number'] = 'N773CK';

        $parsed = $this->merge([$first], [$second]);

        $this->assertCount(1, $parsed['calendar_events']);
        $this->assertNull($parsed['calendar_events'][0]['metadata']['tail_number']);
        $this->assertSame('metadata.tail_number', $parsed['merge_conflicts'][0]['field']);
        $this->assertEqualsCanonicalizing(['N772CK', 'N773CK'], array_column($parsed['merge_conflicts'][0]['observations'], 'value'));
    }

    public function test_it_flags_overlapping_time_observations_without_combining_them(): void
    {
        $first = self::flight();
        $second = $first;
        $second['start'] = '2026-06-16T00:45:00+00:00';
        $second['end'] = '2026-06-16T04:45:00+00:00';

        $parsed = $this->merge([$first], [$second]);

        $this->assertCount(2, $parsed['calendar_events']);
        $this->assertCount(1, $parsed['merge_conflicts']);
        $this->assertSame('event', $parsed['merge_conflicts'][0]['kind']);
        $this->assertCount(2, $parsed['merge_conflicts'][0]['observations']);
    }

    public function test_it_preserves_incompatible_structured_details_without_inventing_combined_records(): void
    {
        $first = self::flight();
        $second = $first;
        $first['metadata']['crew'] = [['employee_id' => '123', 'base' => 'LAX']];
        $second['metadata']['crew'] = [['employee_id' => '123', 'base' => 'ANC']];

        $parsed = $this->merge([$first], [$second]);

        $this->assertCount(1, $parsed['calendar_events']);
        $this->assertNull($parsed['calendar_events'][0]['metadata']['crew']);
        $this->assertSame('metadata.crew', $parsed['merge_conflicts'][0]['field']);
        $this->assertCount(2, $parsed['merge_conflicts'][0]['observations']);
    }

    public function test_it_reconciles_all_source_summaries_independently_of_upload_order(): void
    {
        $results = [
            ['trip' => ['trip_number' => '13131', 'annotations' => ['One']], 'calendar_events' => [self::flight()]],
            ['trip' => ['annotations' => ['Two'], 'trip_number' => '13131', 'crew_count' => 4], 'calendar_events' => [self::flight()]],
        ];
        $sources = $this->sources();
        $merger = new ScheduleResultMerger(new FlightMapper);

        $original = $merger->merge($results, $sources);
        $reversed = $merger->merge(array_reverse($results), array_reverse($sources));

        $this->assertSame($original, $reversed);
        $this->assertSame(['One', 'Two'], $original['trip']['annotations']);
        $this->assertSame(4, $original['trip']['crew_count']);
        $this->assertSame([], $original['merge_conflicts']);
        $this->assertEqualsCanonicalizing([['page_count' => 1], ['page_count' => 2]], array_column($original['sources'], 'meta'));
        $this->assertCount(2, $original['sources']);
    }

    /** @return array<string, mixed> */
    private static function event(string $type, array $metadata = []): array
    {
        return [
            'title' => 'Scheduled assignment',
            'type' => $type,
            'start' => '2026-06-15T23:45:00+00:00',
            'end' => '2026-06-16T03:45:00+00:00',
            'timezone' => 'UTC',
            'metadata' => $metadata,
        ];
    }

    /** @return array<string, mixed> */
    private static function flight(): array
    {
        return self::event('flight', ['flight_number' => 'CKS 240', 'origin' => 'ICN', 'destination' => 'HKG']);
    }

    /** @return array<string, mixed> */
    private function merge(array $first, array $second): array
    {
        return (new ScheduleResultMerger(new FlightMapper))->merge([
            ['trip' => [], 'calendar_events' => $first],
            ['trip' => [], 'calendar_events' => $second],
        ], $this->sources());
    }

    /** @return list<array<string, mixed>> */
    private function sources(): array
    {
        return [
            ['filename' => 'first.png', 'raw_text' => 'First source', 'position' => 1, 'meta' => ['page_count' => 1]],
            ['filename' => 'second.png', 'raw_text' => 'Second source', 'position' => 2, 'meta' => ['page_count' => 2]],
        ];
    }
}
