<?php

namespace Tests\Feature;

use App\Mail\SecurityUpdateAnnouncement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendSecurityUpdateAnnouncementCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Cache::forget('mailings:security-update:queued');
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_full_send_queues_only_active_users_and_blocks_a_repeat(): void
    {
        Carbon::setTestNow('2026-10-22 12:00:00');
        Cache::forget('mailings:security-update:queued');
        Mail::fake();

        $firstUser = User::factory()->create(['name' => 'First User']);
        $secondUser = User::factory()->unverified()->create(['name' => 'Second User']);
        User::factory()->create(['is_active' => false]);

        $this->artisan('mail:security-update', ['--all' => true])
            ->expectsConfirmation('Queue the security update for 2 users?', 'yes')
            ->assertSuccessful();

        Mail::assertQueued(SecurityUpdateAnnouncement::class, 2);

        foreach ([$firstUser, $secondUser] as $index => $user) {
            $message = Mail::queued(SecurityUpdateAnnouncement::class)->values()[$index];

            $this->assertSame('broadcasts', $message->queue);
            $this->assertTrue($message->delay->equalTo(now()->addSeconds($index * 4)));
            $this->assertTrue($message->hasTo($user->email));
        }

        $this->artisan('mail:security-update', ['--all' => true])->assertFailed();
        Mail::assertQueued(SecurityUpdateAnnouncement::class, 2);
    }

    public function test_review_copy_and_missing_or_conflicting_options(): void
    {
        Mail::fake();

        $this->artisan('mail:security-update')->assertFailed();
        $this->artisan('mail:security-update', ['--email' => 'invalid'])->assertFailed();
        $this->artisan('mail:security-update', ['--email' => 'review@example.com', '--all' => true])->assertFailed();
        Mail::assertNothingQueued();

        $this->artisan('mail:security-update', ['--email' => 'review@example.com'])->assertSuccessful();
        Mail::assertQueued(SecurityUpdateAnnouncement::class, fn (SecurityUpdateAnnouncement $message): bool => $message->hasTo('review@example.com'));
    }

    public function test_declined_full_send_queues_nothing(): void
    {
        Cache::forget('mailings:security-update:queued');
        Mail::fake();
        User::factory()->create();

        $this->artisan('mail:security-update', ['--all' => true])
            ->expectsConfirmation('Queue the security update for 1 user?', 'no')
            ->assertSuccessful();

        Mail::assertNothingQueued();
        $this->assertFalse(Cache::has('mailings:security-update:queued'));
    }
}
