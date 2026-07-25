<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        Gate::define('use-schedule-extractor', fn (User $user): bool => $user->canUseScheduleExtractor());
        Gate::define('export-schedule-extractor-duty', fn (User $user): bool => $user->canExportScheduleExtractorDuty());
        Gate::define('use-flight-release', fn (User $user): bool => $user->canUseFlightRelease());
    }
}
