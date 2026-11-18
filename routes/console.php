<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('app:cleanup-user-uploads')->daily();
Schedule::command('system:check-alerts')->hourly()->onOneServer()->withoutOverlapping();
Schedule::command('crew-bids:import-portal')->hourly()
    ->when(fn (): bool => (bool) config('crew_bids.portal.enabled') && (now('UTC')->day >= 8 && now('UTC')->day <= 16 || now('UTC')->hour % 6 === 0))
    ->onOneServer()->withoutOverlapping(10);
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
