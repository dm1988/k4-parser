<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_save_encrypted_pay_settings_and_omitting_them_preserves_values(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->withCrewPaySettings('2020-03-01')->create();

        $this->actingAs($user)->patch(route('profile.update'), ['name' => $user->name, 'email' => $user->email,
            'user_id' => $other->id, 'crew_pay_settings' => ['longevity_date' => '2020-02-29', 'check_airman' => '1']])
            ->assertSessionHasNoErrors()->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertSame(['longevity_date' => '2020-02-29', 'check_airman' => true], $user->crew_pay_settings);
        $raw = DB::table('users')->where('id', $user->id)->value('crew_pay_settings');
        $this->assertStringNotContainsString('2020-02-29', $raw);
        $this->assertSame($user->crew_pay_settings, json_decode(Crypt::decryptString($raw), true));
        $this->assertArrayNotHasKey('crew_pay_settings', $user->toArray());
        $this->assertSame(['longevity_date' => '2020-03-01', 'check_airman' => false], $other->fresh()->crew_pay_settings);
        $this->get(route('profile.edit'))->assertOk()->assertSee('Adjusted pay-longevity date (UTC)')
            ->assertSee('Check Airman override')->assertSee('value="2020-02-29"', false);

        $this->patch(route('profile.update'), ['name' => $user->name, 'email' => $user->email])->assertSessionHasNoErrors();
        $this->assertSame(['longevity_date' => '2020-02-29', 'check_airman' => true], $user->fresh()->crew_pay_settings);
    }

    #[DataProvider('invalidPaySettings')]
    public function test_invalid_profile_pay_settings_are_rejected(array $settings, string $field): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-03 00:00:00', 'UTC'));
        $user = User::factory()->withCrewPaySettings('2020-01-01')->create();

        $this->actingAs($user)->from(route('profile.edit'))->patch(route('profile.update'), [
            'name' => $user->name, 'email' => $user->email, 'crew_pay_settings' => $settings,
        ])->assertSessionHasErrors($field)->assertRedirect(route('profile.edit'));

        $this->assertSame(['longevity_date' => '2020-01-01', 'check_airman' => false], $user->fresh()->crew_pay_settings);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidPaySettings(): array
    {
        return [
            'invalid calendar date' => [['longevity_date' => '2026-02-30', 'check_airman' => false], 'crew_pay_settings.longevity_date'],
            'future UTC date' => [['longevity_date' => '2026-11-04', 'check_airman' => false], 'crew_pay_settings.longevity_date'],
            'invalid flag' => [['longevity_date' => '2020-01-01', 'check_airman' => 'yes'], 'crew_pay_settings.check_airman'],
            'unknown field' => [['longevity_date' => '2020-01-01', 'check_airman' => false, 'user_id' => 999], 'crew_pay_settings'],
            'missing flag' => [['longevity_date' => '2020-01-01'], 'crew_pay_settings.check_airman'],
        ];
    }

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
        $response->assertSee('Flight Tracker')->assertSee('Flightradar24')->assertSee('iCal downloads');
        $response->assertSeeInOrder(['User preferences', 'Schedule Extractor', 'Flight Tracker', 'K4 Bids', 'Bid seat']);
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_update_schedule_airline_code_preferences(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'airline_iata_code' => 'ab',
                'airline_icao_code' => 'xyz',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('AB', $user->airline_iata_code);
        $this->assertSame('XYZ', $user->airline_icao_code);
    }

    public function test_schedule_airline_code_preferences_must_be_valid(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/profile')
            ->patch('/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'airline_iata_code' => 'TOO',
                'airline_icao_code' => '12!',
            ])
            ->assertSessionHasErrors(['airline_iata_code', 'airline_icao_code'])
            ->assertRedirect('/profile');
    }

    #[DataProvider('trackers')]
    public function test_users_can_save_their_flight_tracker_preference(string $tracker): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name, 'email' => $user->email, 'flight_tracker_preference' => $tracker,
        ])->assertSessionHasNoErrors()->assertRedirect('/profile');

        $this->assertSame($tracker, $user->refresh()->flight_tracker_preference);
        $this->get('/profile')->assertSee('value="'.$tracker.'" selected', false);
    }

    public static function trackers(): array
    {
        return [['flightaware'], ['flightradar24']];
    }

    #[DataProvider('invalidTrackers')]
    public function test_invalid_tracker_preferences_are_rejected(mixed $value): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->from('/profile')->patch('/profile', [
            'name' => $user->name, 'email' => $user->email, 'flight_tracker_preference' => $value,
        ])->assertSessionHasErrors('flight_tracker_preference')->assertRedirect('/profile');

        $this->assertSame('flightaware', $user->refresh()->flight_tracker_preference);
    }

    public static function invalidTrackers(): array
    {
        return [['invalid'], [''], [null], [['flightaware']]];
    }

    public function test_updating_other_profile_fields_preserves_the_tracker_preference(): void
    {
        $user = User::factory()->create(['flight_tracker_preference' => 'flightradar24']);
        $this->actingAs($user)->patch('/profile', ['name' => 'Updated Name', 'email' => $user->email])
            ->assertSessionHasNoErrors();

        $this->assertSame('flightradar24', $user->refresh()->flight_tracker_preference);
    }

    public function test_database_default_applies_when_the_preference_is_not_inserted(): void
    {
        $user = User::factory()->make();
        unset($user->flight_tracker_preference);
        $user->save();

        $this->assertSame('flightaware', $user->refresh()->flight_tracker_preference);
    }

    #[DataProvider('bidSeats')]
    public function test_users_can_save_their_bid_seat_preference(string $seat): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name, 'email' => $user->email, 'crew_bid_seat_preference' => $seat,
            'user_id' => $other->id,
        ])->assertSessionHasNoErrors()->assertRedirect('/profile');

        $this->assertSame($seat, $user->refresh()->crew_bid_seat_preference);
        $this->assertSame('FO', $other->refresh()->crew_bid_seat_preference);
        $this->assertNotNull($user->email_verified_at);
        $this->get('/profile')->assertOk()->assertSee('value="'.$seat.'" selected', false);
    }

    public static function bidSeats(): array
    {
        return ['first officer' => ['FO'], 'captain' => ['CA']];
    }

    #[DataProvider('invalidBidSeats')]
    public function test_invalid_bid_seats_do_not_change_user_preferences(mixed $seat): void
    {
        $user = User::factory()->create(['crew_bid_seat_preference' => 'CA']);

        $this->actingAs($user)->from('/profile')->patch('/profile', [
            'name' => 'Unsaved Name', 'email' => $user->email, 'crew_bid_seat_preference' => $seat,
        ])->assertSessionHasErrors('crew_bid_seat_preference')->assertRedirect('/profile');

        $this->assertSame('CA', $user->refresh()->crew_bid_seat_preference);
        $this->assertNotSame('Unsaved Name', $user->name);
    }

    public static function invalidBidSeats(): array
    {
        return ['unsupported' => ['FE'], 'empty' => [''], 'null' => [null], 'array' => [['FO']]];
    }

    public function test_updating_other_profile_fields_preserves_the_bid_seat(): void
    {
        $user = User::factory()->create(['crew_bid_seat_preference' => 'CA']);

        $this->actingAs($user)->patch('/profile', ['name' => 'Updated Name', 'email' => $user->email])
            ->assertSessionHasNoErrors();

        $this->assertSame('CA', $user->refresh()->crew_bid_seat_preference);
    }

    public function test_guests_cannot_save_bid_preferences(): void
    {
        $this->patch('/profile', ['crew_bid_seat_preference' => 'CA'])->assertRedirect('/login');
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
