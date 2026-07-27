<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Schedule\ScheduleAirlineCodes;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ScheduleAirlineCodesTest extends TestCase
{
    public function test_it_uses_configured_airline_codes_when_the_user_has_no_preferences(): void
    {
        Config::set('schedule.airline_codes.iata', 'K4');
        Config::set('schedule.airline_codes.icao', 'CKS');

        $codes = app(ScheduleAirlineCodes::class);

        $this->assertSame('K4', $codes->iata());
        $this->assertSame('CKS', $codes->icao());
    }

    public function test_user_preferences_override_configured_airline_codes(): void
    {
        Config::set('schedule.airline_codes.iata', 'K4');
        Config::set('schedule.airline_codes.icao', 'CKS');
        $this->actingAs(User::factory()->make([
            'airline_iata_code' => 'ab',
            'airline_icao_code' => 'xyz',
        ]));

        $codes = app(ScheduleAirlineCodes::class);

        $this->assertSame('AB', $codes->iata());
        $this->assertSame('XYZ', $codes->icao());
    }
}
