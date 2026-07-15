<?php

namespace Tests\Feature;

use App\Models\User;
use App\View\Models\Parser\ParserResultViewModel;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ParserResultComponentTest extends TestCase
{
    public function test_it_renders_the_export_button_in_the_header_without_helper_copy(): void
    {
        $html = Blade::render('<x-parser.result :model="$model" />', [
            'model' => ParserResultViewModel::fromArray([
                'source' => 'text',
                'parse_key' => '01KXK47PE0HNRXE4VV2N8K8N58',
                'filters' => [],
                'parsed' => [
                    'trip' => [
                        'trip_number' => '13131',
                    ],
                    'calendar_events' => [
                        [
                            'type' => 'layover',
                            'title' => 'Layover ICN',
                            'start' => '2026-07-02T23:59:00+00:00',
                            'end' => '2026-07-04T09:00:00+00:00',
                            'metadata' => [],
                            'download_id' => '01JTESTEVENTKEYABC123',
                        ],
                    ],
                ],
            ]),
        ]);

        $this->assertStringContainsString('Parsed Output', $html);
        $this->assertStringContainsString('Download all (.ics)', $html);
        $this->assertStringNotContainsString('Download the parsed events as a calendar file.', $html);
    }

    public function test_it_hides_raw_json_from_normal_users(): void
    {
        $this->actingAs(User::factory()->make([
            'role' => 'user',
        ]));

        $html = Blade::render('<x-parser.result :model="$model" />', [
            'model' => $this->makeResultModel(),
        ]);

        $this->assertStringNotContainsString('Raw JSON', $html);
    }

    public function test_it_shows_raw_json_to_admin_users(): void
    {
        $this->actingAs(User::factory()->make([
            'role' => 'admin',
        ]));

        $html = Blade::render('<x-parser.result :model="$model" />', [
            'model' => $this->makeResultModel(),
        ]);

        $this->assertStringContainsString('Raw JSON', $html);
    }

    private function makeResultModel(): ParserResultViewModel
    {
        return ParserResultViewModel::fromArray([
            'source' => 'text',
            'parse_key' => '01KXK47PE0HNRXE4VV2N8K8N58',
            'filters' => [],
            'parsed' => [
                'trip' => [
                    'trip_number' => '13131',
                ],
                'calendar_events' => [
                    [
                        'type' => 'layover',
                        'title' => 'Layover ICN',
                        'start' => '2026-07-02T23:59:00+00:00',
                        'end' => '2026-07-04T09:00:00+00:00',
                        'metadata' => [],
                        'download_id' => '01JTESTEVENTKEYABC123',
                    ],
                ],
            ],
        ]);
    }
}
