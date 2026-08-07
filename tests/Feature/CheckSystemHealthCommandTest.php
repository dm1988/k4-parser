<?php

namespace Tests\Feature;

use App\Models\ExtractRequest;
use App\Models\User;
use App\Notifications\SystemAlertNotification;
use App\Services\Infrastructure\DiskSpaceMonitor;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CheckSystemHealthCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-07 12:00:00');
        Config::set('mail.admin_address', 'operations@example.com');
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_command_fails_when_the_admin_email_is_not_configured(): void
    {
        Config::set('mail.admin_address');
        Notification::fake();

        $this->artisan('system:check-alerts')
            ->expectsOutput('MAIL_ADMIN_ADDRESS must be configured with a valid email address.')
            ->assertFailed();

        Notification::assertNothingSent();
    }

    public function test_warning_alerts_are_sent_every_twelve_hours_while_the_condition_persists(): void
    {
        Notification::fake();
        $this->mockDiskPercentage(15.0);

        $this->artisan('system:check-alerts')->assertSuccessful();
        $this->artisan('system:check-alerts')->assertSuccessful();

        Notification::assertSentOnDemandTimes(SystemAlertNotification::class, 1);

        Carbon::setTestNow(now()->addHours(12)->addSecond());

        $this->artisan('system:check-alerts')->assertSuccessful();

        Notification::assertSentOnDemandTimes(SystemAlertNotification::class, 2);
        Notification::assertSentOnDemand(
            SystemAlertNotification::class,
            fn (SystemAlertNotification $notification): bool => $notification->level === 'warning'
                && $notification->type === 'Disk Space',
        );
    }

    public function test_critical_disk_alert_escalates_immediately_and_uses_a_one_hour_throttle(): void
    {
        Notification::fake();
        $this->mockDiskPercentage(15.0, 5.0, 5.0, 5.0);

        $this->artisan('system:check-alerts')->assertSuccessful();
        $this->artisan('system:check-alerts')->assertSuccessful();
        $this->artisan('system:check-alerts')->assertSuccessful();

        Notification::assertSentOnDemandTimes(SystemAlertNotification::class, 2);

        Carbon::setTestNow(now()->addHour()->addSecond());

        $this->artisan('system:check-alerts')->assertSuccessful();

        Notification::assertSentOnDemandTimes(SystemAlertNotification::class, 3);
        Notification::assertSentOnDemand(
            SystemAlertNotification::class,
            fn (SystemAlertNotification $notification): bool => $notification->level === 'critical'
                && $notification->type === 'Disk Space',
        );
    }

    public function test_a_resolved_alert_can_notify_again_as_a_new_incident(): void
    {
        Notification::fake();
        $this->mockDiskPercentage(15.0, 25.0, 15.0);

        $this->artisan('system:check-alerts')->assertSuccessful();
        $this->artisan('system:check-alerts')->assertSuccessful();
        $this->artisan('system:check-alerts')->assertSuccessful();

        Notification::assertSentOnDemandTimes(SystemAlertNotification::class, 2);
    }

    public function test_signup_spike_is_compared_with_the_preceding_thirty_day_baseline(): void
    {
        Notification::fake();
        $this->mockDiskPercentage(50.0);
        User::factory()->create(['created_at' => now()->subDays(2)]);
        User::factory()->count(7)->create(['created_at' => now()->subHour()]);

        $this->artisan('system:check-alerts')->assertSuccessful();

        Notification::assertSentOnDemand(SystemAlertNotification::class, function (
            SystemAlertNotification $notification,
            array $channels,
            AnonymousNotifiable $notifiable,
        ): bool {
            return $notification->type === 'User Signups'
                && $notification->level === 'warning'
                && str_contains($notification->message, '7 (threshold: 6)')
                && $channels === ['mail']
                && $notifiable->routes['mail'] === 'operations@example.com';
        });
    }

    public function test_extract_request_spike_is_compared_with_the_preceding_thirty_day_baseline(): void
    {
        Notification::fake();
        $this->mockDiskPercentage(50.0);
        $this->createExtractRequest(now()->subDays(2));

        foreach (range(1, 7) as $index) {
            $this->createExtractRequest(now()->subMinutes($index));
        }

        $this->artisan('system:check-alerts')->assertSuccessful();

        Notification::assertSentOnDemand(
            SystemAlertNotification::class,
            fn (SystemAlertNotification $notification): bool => $notification->type === 'Extract Requests'
                && str_contains($notification->message, '7 (threshold: 6)'),
        );
    }

    public function test_activity_without_a_historical_baseline_does_not_send_a_volume_alert(): void
    {
        Notification::fake();
        $this->mockDiskPercentage(50.0);
        User::factory()->count(7)->create(['created_at' => now()->subHour()]);

        $this->artisan('system:check-alerts')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_system_alert_notification_is_queued_and_builds_the_expected_mail(): void
    {
        $notification = new SystemAlertNotification(
            type: 'Disk Space',
            level: 'critical',
            message: 'Disk space is critically low.',
        );
        $mail = $notification->toMail(new AnonymousNotifiable);

        $this->assertInstanceOf(ShouldQueue::class, $notification);
        $this->assertSame('[CRITICAL] Disk Space Alert', $mail->subject);
        $this->assertContains('Disk space is critically low.', $mail->introLines);
        $this->assertSame('View Admin Dashboard', $mail->actionText);
        $this->assertSame(route('filament.admin.pages.dashboard'), $mail->actionUrl);
    }

    public function test_system_health_command_is_scheduled_hourly_without_overlap_on_one_server(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains((string) $event->command, 'system:check-alerts'));

        $this->assertNotNull($event);
        $this->assertSame('0 * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertTrue($event->onOneServer);
    }

    private function mockDiskPercentage(float ...$percentages): void
    {
        $this->mock(DiskSpaceMonitor::class)
            ->shouldReceive('freePercentage')
            ->andReturn(...$percentages);
    }

    private function createExtractRequest(Carbon $createdAt): ExtractRequest
    {
        $extractRequest = ExtractRequest::query()->create([
            'request_uuid' => fake()->uuid(),
            'source_type' => 'pdf',
            'parser_type' => 'flight_plan',
            'status' => 'success',
            'extraction_duration_ms' => 10,
            'detected_event_count' => 1,
            'detected_flight_count' => 1,
            'detected_hotel_count' => 0,
        ]);
        $extractRequest->setCreatedAt($createdAt)->save();

        return $extractRequest;
    }
}
