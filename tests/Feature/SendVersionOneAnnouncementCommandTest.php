<?php

namespace Tests\Feature;

use App\Mail\VersionOneAnnouncement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendVersionOneAnnouncementCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Cache::forget('mailings:version-one-announcement:queued');
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_full_send_queues_broadcasts_four_seconds_apart(): void
    {
        Carbon::setTestNow('2026-08-03 12:00:00');
        Cache::forget('mailings:version-one-announcement:queued');
        Mail::fake();

        $firstUser = User::factory()->create(['name' => 'First User']);
        $secondUser = User::factory()->unverified()->create(['name' => 'Second User']);
        $thirdUser = User::factory()->create(['name' => 'Third User']);

        $this->artisan('mail:version-one', ['--all' => true])
            ->expectsConfirmation('Queue the announcement for 3 users?', 'yes')
            ->assertSuccessful();

        Mail::assertQueued(VersionOneAnnouncement::class, 3);

        $queuedMailables = Mail::queued(VersionOneAnnouncement::class)->values();
        $users = [$firstUser, $secondUser, $thirdUser];

        foreach ($queuedMailables as $index => $mailable) {
            $this->assertSame('broadcasts', $mailable->queue);
            $this->assertTrue($mailable->delay->equalTo(now()->addSeconds($index * 4)));
            $this->assertTrue($mailable->hasTo($users[$index]->email));
        }
    }
}
