<?php

namespace Tests\Unit\DTOs;

use App\DTOs\ReleaseStatusData;
use App\Enums\ReleaseStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ReleaseStatusDataTest extends TestCase
{
    public function test_serializes_status_and_evidence_without_exposing_evidence_in_display_data(): void
    {
        $data = new ReleaseStatusData(ReleaseStatus::Preliminary, 'original private warning');

        $this->assertSame(['status' => 'preliminary', 'sourceWarning' => 'original private warning'], json_decode(json_encode($data, JSON_THROW_ON_ERROR), true));
        $this->assertSame($data->toArray(), ReleaseStatusData::fromArray($data->toArray())->toArray());
        $this->assertSame([
            'preliminary' => true,
            'label' => 'Preliminary flight plan',
            'warning' => 'Payload estimated — Do not fuel aircraft',
        ], $data->display());
    }

    #[DataProvider('invalidMetadata')]
    public function test_legacy_and_malformed_metadata_remain_unknown(mixed $metadata): void
    {
        $data = ReleaseStatusData::fromArray($metadata);

        $this->assertSame(ReleaseStatus::Unknown, $data->status);
        $this->assertNull($data->sourceWarning);
        $this->assertSame('Release status not determined', $data->display()['label']);
    }

    /** @return array<string, array{mixed}> */
    public static function invalidMetadata(): array
    {
        return [
            'absent' => [null], 'scalar' => ['preliminary'], 'empty' => [[]],
            'invalid status' => [['status' => 'final']],
            'wrong status type' => [['status' => []]],
            'missing evidence' => [['status' => 'preliminary']],
            'empty evidence' => [['status' => 'preliminary', 'sourceWarning' => '  ']],
            'wrong evidence type' => [['status' => 'preliminary', 'sourceWarning' => []]],
            'inconsistent evidence' => [['status' => 'not_marked_preliminary', 'sourceWarning' => 'warning']],
        ];
    }

    public function test_an_unmarked_release_is_distinct_from_unknown_and_has_no_final_badge(): void
    {
        $data = ReleaseStatusData::fromArray(['status' => 'not_marked_preliminary', 'sourceWarning' => null]);

        $this->assertSame(ReleaseStatus::NotMarkedPreliminary, $data->status);
        $this->assertNull($data->display()['label']);
    }
}
