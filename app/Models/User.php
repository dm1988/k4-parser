<?php

namespace App\Models;

use App\Notifications\VerifyEmailWithOtp;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/** @property Carbon|null $email_verification_otp_expires_at */
#[Fillable(['name', 'email', 'email_verified_at', 'password', 'remember_token', 'role', 'is_active', 'last_admin_login_at', 'stripe_id', 'pm_type', 'pm_last_four', 'trial_ends_at'])]
#[Hidden(['password', 'remember_token', 'email_verification_otp_hash'])]
class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'email_verification_otp_expires_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function sendEmailVerificationNotification(): void
    {
        $otp = (string) random_int(100000, 999999);

        $this->forceFill([
            'email_verification_otp_hash' => self::hashEmailVerificationOtp($otp),
            'email_verification_otp_expires_at' => now()->addMinutes(15),
        ])->save();

        $this->notify(new VerifyEmailWithOtp($otp));
    }

    public static function hashEmailVerificationOtp(string $otp): string
    {
        return hash_hmac('sha256', $otp, (string) config('app.key'));
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() !== 'admin') {
            return false;
        }

        return $this->is_active
            && $this->email_verified_at !== null
            && $this->isAdmin();
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super_admin'], true);
    }

    public function canUseFlightRelease(): bool
    {
        return $this->canUseConfiguredFeature(
            enabled: (bool) config('features.flight_release.enabled', true),
            forAllUsers: (bool) config('features.flight_release.for_all_users', false),
        );
    }

    public function canUseScheduleParser(): bool
    {
        if (! $this->hasVerifiedEmail()) {
            return false;
        }

        return $this->canUseConfiguredFeature(
            enabled: (bool) config('features.schedule_parser.enabled', false),
            forAllUsers: (bool) config('features.schedule_parser.for_all_users', false),
        );
    }

    public function canExportScheduleParserDuty(): bool
    {
        return $this->canUseConfiguredFeature(
            enabled: (bool) config('features.schedule_parser.enabled', true),
            forAllUsers: (bool) config('features.schedule_parser.duty_export_for_all_users', false),
        );
    }

    private function canUseConfiguredFeature(bool $enabled, bool $forAllUsers): bool
    {
        if (! $enabled) {
            return false;
        }

        return $forAllUsers || $this->isAdmin();
    }
}
