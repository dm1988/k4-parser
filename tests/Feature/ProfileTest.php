<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
        $response->assertSee('Flight Tracker')->assertSee('Flightradar24')->assertSee('iCal downloads');
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
