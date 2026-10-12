<?php

namespace Tests\Feature;

use App\DTOs\DutyEvent;
use App\DTOs\ExtractedResultData;
use App\Enums\DutyEventType;
use App\View\Models\Extract\ExtractPageViewModel;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DutyCardComponentTest extends TestCase
{
    #[DataProvider('dutyTypes')]
    public function test_it_renders_specific_duty_badges_from_saved_calendar_events(DutyEventType $type): void
    {
        $model = ExtractPageViewModel::fromResult(ExtractedResultData::fromArray([
            'parse_key' => 'parse-id',
            'parsed' => ['calendar_events' => [[
                'type' => 'duty',
                'title' => $type->label().' LAX',
                'start' => '2026-10-10T07:00:00Z',
                'end' => '2026-10-10T15:00:00Z',
                'download_id' => 'event-id',
                'metadata' => ['duty_type' => $type->value, 'station' => 'LAX'],
            ]]],
        ]));

        $event = $model->result->events[0];
        $this->assertInstanceOf(DutyEvent::class, $event);
        $html = Blade::render('<x-extract.event-card :event="$event" />', ['event' => $event]);

        $this->assertStringContainsString($type->badgeColor(), $html);
        $this->assertMatchesRegularExpression('/<span[^>]*>\s*'.preg_quote($type->label(), '/').'\s*<\/span>/', $html);
        $this->assertStringContainsString('0700 Z - 1500 Z', $html);
        $this->assertStringContainsString('event-id', $html);

        if ($type !== DutyEventType::GeneralDuty) {
            $this->assertDoesNotMatchRegularExpression('/<span[^>]*>\s*Duty\s*<\/span>/', $html);
        }
    }

    public static function dutyTypes(): array
    {
        return array_map(static fn (DutyEventType $type): array => [$type], DutyEventType::cases());
    }
}
