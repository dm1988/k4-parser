<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\VerifyEmailWithOtp;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_screen_can_be_rendered(): void
    {
        $user = User::factory()->unverified()->create([
            'email_verification_otp_hash' => User::hashEmailVerificationOtp('149822'),
            'email_verification_otp_expires_at' => now()->addMinutes(15),
        ]);

        $response = $this->actingAs($user)->get('/verify-email');

        $response->assertStatus(200);
        $response->assertSee('Waiting for verification...');
        $response->assertSee('Enter 6-digit code');
    }

    public function test_email_can_be_verified(): void
    {
        $user = User::factory()->unverified()->create([
            'email_verification_otp_hash' => User::hashEmailVerificationOtp('149822'),
            'email_verification_otp_expires_at' => now()->addMinutes(15),
        ]);

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        Event::assertDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertNull($user->fresh()->email_verification_otp_hash);
        $this->assertNull($user->fresh()->email_verification_otp_expires_at);
        $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
    }

    public function test_email_is_not_verified_with_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')]
        );

        $this->actingAs($user)->get($verificationUrl);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_verification_email_includes_hashed_otp(): void
    {
        $user = User::factory()->unverified()->create();

        Notification::fake();

        $user->sendEmailVerificationNotification();

        Notification::assertSentTo($user, VerifyEmailWithOtp::class);

        /** @var VerifyEmailWithOtp $notification */
        $notification = Notification::sent($user, VerifyEmailWithOtp::class)->first();
        $freshUser = $user->fresh();

        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $notification->otp);
        $this->assertNotSame($notification->otp, $freshUser->email_verification_otp_hash);
        $this->assertSame(
            User::hashEmailVerificationOtp($notification->otp),
            $freshUser->email_verification_otp_hash,
        );
        $this->assertTrue($freshUser->email_verification_otp_expires_at->between(
            now()->addMinutes(14),
            now()->addMinutes(15),
        ));
    }

    public function test_email_can_be_verified_with_an_otp_only_once(): void
    {
        $user = User::factory()->unverified()->create([
            'email_verification_otp_hash' => User::hashEmailVerificationOtp('149822'),
            'email_verification_otp_expires_at' => now()->addMinutes(15),
        ]);

        Event::fake();

        $response = $this->actingAs($user)->post(route('verification.verify-otp'), [
            'otp' => '149822',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
        Event::assertDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertNull($user->fresh()->email_verification_otp_hash);
        $this->assertNull($user->fresh()->email_verification_otp_expires_at);
    }

    public function test_invalid_otp_does_not_verify_email(): void
    {
        $user = User::factory()->unverified()->create([
            'email_verification_otp_hash' => User::hashEmailVerificationOtp('149822'),
            'email_verification_otp_expires_at' => now()->addMinutes(15),
        ]);

        $response = $this->actingAs($user)->from(route('verification.notice'))->post(route('verification.verify-otp'), [
            'otp' => '000000',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $response->assertSessionHasErrors('otp');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_expired_otp_is_rejected_and_destroyed(): void
    {
        $user = User::factory()->unverified()->create([
            'email_verification_otp_hash' => User::hashEmailVerificationOtp('149822'),
            'email_verification_otp_expires_at' => now()->subSecond(),
        ]);

        $response = $this->actingAs($user)->post(route('verification.verify-otp'), [
            'otp' => '149822',
        ]);

        $response->assertSessionHasErrors('otp');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->assertNull($user->fresh()->email_verification_otp_hash);
        $this->assertNull($user->fresh()->email_verification_otp_expires_at);
    }

    public function test_resending_verification_email_replaces_the_previous_otp(): void
    {
        $user = User::factory()->unverified()->create();

        Notification::fake();

        $user->sendEmailVerificationNotification();
        $oldHash = $user->fresh()->email_verification_otp_hash;
        $user->sendEmailVerificationNotification();

        $this->assertNotSame($oldHash, $user->fresh()->email_verification_otp_hash);
        Notification::assertSentToTimes($user, VerifyEmailWithOtp::class, 2);
    }

    public function test_otp_verification_is_rate_limited_after_five_attempts(): void
    {
        $user = User::factory()->unverified()->create([
            'email_verification_otp_hash' => User::hashEmailVerificationOtp('149822'),
            'email_verification_otp_expires_at' => now()->addMinutes(15),
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->actingAs($user)->post(route('verification.verify-otp'), ['otp' => '000000']);
        }

        $this->actingAs($user)
            ->post(route('verification.verify-otp'), ['otp' => '000000'])
            ->assertTooManyRequests();
    }
}
