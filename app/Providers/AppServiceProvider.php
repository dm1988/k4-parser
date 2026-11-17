<?php

namespace App\Providers;

use App\Models\CrewBidCredentialVault;
use App\Models\User;
use App\Services\CrewBids\CrewBidOperationGuard;
use App\Services\CrewBids\CrewBidSecretStore;
use App\Services\CrewBids\CrewBidVaultService;
use App\Services\Schedule\FlightTrackerUrlGenerator;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Validated;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(FlightTrackerUrlGenerator::class);
        $this->app->scoped(CrewBidOperationGuard::class);
        $this->app->scoped(CrewBidVaultService::class);
        $this->app->scoped(CrewBidSecretStore::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        DevCommands::artisan('queue:listen crew-bid-refreshes --queue=crew-bid-refreshes --tries=1 --timeout=240', 'bids');

        Model::shouldBeStrict(! $this->app->isProduction());
        RateLimiter::for('crew-bid-vault', fn (Request $request): array => [
            Limit::perMinute(5)->by('user:'.$request->user()?->id),
            Limit::perMinute(20)->by('ip:'.$request->ip()),
        ]);
        View::composer('components.crew-bids.portal-credentials', function (\Illuminate\View\View $view): void {
            $user = request()->user();
            $connection = ['connected' => false, 'unlocked' => false, 'hint' => null];
            if (config('crew_bids.vault.enabled') && $user instanceof User && $user->canUseCrewBids()) {
                $connection = app(CrewBidVaultService::class)->status($user, request()->session());
            }
            $view->with('connection', $connection);
        });

        User::updating(function (User $user): void {
            if ($user->isDirty(['password', 'role', 'is_active', 'crew_bids_access', 'email', 'email_verified_at']) || $this->employeeIdentityChanged($user)) {
                $user->crew_bid_credential_epoch = Str::random(32);
            }
        });
        User::updated(function (User $user): void {
            if (($user->wasChanged('password') && ! app(CrewBidVaultService::class)->preservesPasswordVault($user)) || $this->employeeIdentityChanged($user)) {
                CrewBidCredentialVault::query()->where('user_id', $user->id)->delete();
            }
            if ($user->wasChanged('password') && app(CrewBidVaultService::class)->loginRehashOwner === $user->id) {
                app(CrewBidVaultService::class)->loginRehashOwner = null;
            }
        });
        Event::listen(Validated::class, function (Validated $event): void {
            if ($event->user instanceof User) {
                app(CrewBidVaultService::class)->loginRehashOwner = $event->user->id;
            }
        });
        Event::listen([Login::class, Failed::class], function (): void {
            app(CrewBidVaultService::class)->loginRehashOwner = null;
        });
        Event::listen(Logout::class, function (Logout $event): void {
            if ($event->user instanceof User && request()->hasSession()) {
                app(CrewBidVaultService::class)->lock(request()->session(), $event->user->id);
            }
        });
        Event::listen(PasswordReset::class, function (PasswordReset $event): void {
            CrewBidCredentialVault::query()->where('user_id', $event->user->getAuthIdentifier())->delete();
        });

        Gate::define('use-schedule-extractor', fn (User $user): bool => $user->canUseScheduleExtractor());
        Gate::define('export-schedule-extractor-duty', fn (User $user): bool => $user->canExportScheduleExtractorDuty());
        Gate::define('use-flight-release', fn (User $user): bool => $user->canUseFlightRelease());
        Gate::define('use-crew-bids', fn (User $user): bool => $user->canUseCrewBids());
        Gate::define('manage-crew-bids', fn (User $user): bool => $user->canUseCrewBids() && $user->isAdmin());
    }

    private function employeeIdentityChanged(User $user): bool
    {
        return $user->getOriginal('crew_bid_employee_number') !== $user->crew_bid_employee_number
            || ($user->getOriginal('crew_bid_employee_verified_at') !== null && $user->crew_bid_employee_verified_at === null);
    }
}
