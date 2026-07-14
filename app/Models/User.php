<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'email_verified_at', 'password', 'remember_token', 'role', 'is_active', 'last_admin_login_at', 'stripe_id', 'pm_type', 'pm_last_four', 'trial_ends_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
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
        return $this->canUseConfiguredFeature(
            enabled: (bool) config('features.schedule_parser.enabled', true),
            forAllUsers: (bool) config('features.schedule_parser.for_all_users', true),
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
