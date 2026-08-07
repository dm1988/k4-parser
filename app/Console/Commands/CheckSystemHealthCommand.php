<?php

namespace App\Console\Commands;

use App\Models\ExtractRequest;
use App\Models\User;
use App\Notifications\SystemAlertNotification;
use App\Services\Infrastructure\DiskSpaceMonitor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Throwable;

#[Signature('system:check-alerts')]
#[Description('Evaluate system metrics and send throttled email alerts')]
class CheckSystemHealthCommand extends Command
{
    private const int CriticalThrottleHours = 1;

    private const int HistoricalDays = 30;

    private const int SpikeMultiplier = 6;

    private const int WarningThrottleHours = 12;

    public function __construct(private readonly DiskSpaceMonitor $diskSpaceMonitor)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $adminEmail = config('mail.admin_address');

        if (! is_string($adminEmail) || filter_var($adminEmail, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('MAIL_ADMIN_ADDRESS must be configured with a valid email address.');

            return self::FAILURE;
        }

        $this->checkDiskSpace();
        $this->checkUserSignups();
        $this->checkExtractRequests();

        return self::SUCCESS;
    }

    protected function checkDiskSpace(): void
    {
        $freePercentage = $this->diskSpaceMonitor->freePercentage(storage_path());

        if ($freePercentage === null) {
            $this->warn('Disk space could not be determined.');

            return;
        }

        if ($freePercentage < 10) {
            $this->resolveAlert('disk_space_warning');
            $this->sendThrottledAlert(
                key: 'disk_space_critical',
                ttlHours: self::CriticalThrottleHours,
                type: 'Disk Space',
                level: 'critical',
                message: 'Disk space is critically low: '.number_format($freePercentage, 2).'% remaining.',
            );

            return;
        }

        if ($freePercentage < 20) {
            $this->resolveAlert('disk_space_critical');
            $this->sendThrottledAlert(
                key: 'disk_space_warning',
                ttlHours: self::WarningThrottleHours,
                type: 'Disk Space',
                level: 'warning',
                message: 'Disk space is low: '.number_format($freePercentage, 2).'% remaining.',
            );

            return;
        }

        $this->resolveAlert('disk_space_warning');
        $this->resolveAlert('disk_space_critical');
    }

    protected function checkUserSignups(): void
    {
        $this->checkVolumeSpike(
            query: User::query(),
            key: 'user_signups_warning',
            type: 'User Signups',
            message: 'High signup volume in last 24 hours',
        );
    }

    protected function checkExtractRequests(): void
    {
        $this->checkVolumeSpike(
            query: ExtractRequest::query(),
            key: 'extract_requests_warning',
            type: 'Extract Requests',
            message: 'High extract request volume in last 24 hours',
        );
    }

    protected function sendThrottledAlert(
        string $key,
        int $ttlHours,
        string $type,
        string $level,
        string $message,
    ): void {
        $cacheKey = $this->cacheKey($key);

        if (! Cache::add($cacheKey, true, now()->addHours($ttlHours))) {
            return;
        }

        try {
            Notification::route('mail', (string) config('mail.admin_address'))
                ->notify(new SystemAlertNotification($type, $level, $message));
        } catch (Throwable $throwable) {
            Cache::forget($cacheKey);

            throw $throwable;
        }
    }

    private function checkVolumeSpike(
        Builder $query,
        string $key,
        string $type,
        string $message,
    ): void {
        $currentCount = (clone $query)
            ->where('created_at', '>=', now()->subHours(24))
            ->count();
        $historicalMaximum = $this->historicalDailyMaximum($query);
        $threshold = $historicalMaximum * self::SpikeMultiplier;

        if ($historicalMaximum === 0 || $currentCount <= $threshold) {
            $this->resolveAlert($key);

            return;
        }

        $this->sendThrottledAlert(
            key: $key,
            ttlHours: self::WarningThrottleHours,
            type: $type,
            level: 'warning',
            message: "{$message}: {$currentCount} (threshold: {$threshold}).",
        );
    }

    private function historicalDailyMaximum(Builder $query): int
    {
        return (int) ((clone $query)
            ->where('created_at', '>=', now()->subDays(self::HistoricalDays + 1))
            ->where('created_at', '<', now()->subHours(24))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as aggregate')
            ->groupBy('date')
            ->pluck('aggregate')
            ->max() ?? 0);
    }

    private function resolveAlert(string $key): void
    {
        Cache::forget($this->cacheKey($key));
    }

    private function cacheKey(string $key): string
    {
        return "system_alert_throttle:{$key}";
    }
}
