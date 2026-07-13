<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CleanupUserUploadsCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_cleanup_command_deletes_expired_files_from_both_private_disks(): void
    {
        Carbon::setTestNow('2026-07-13 12:00:00');

        Storage::fake('user_uploads');
        Storage::fake('user_flight_releases');

        Storage::disk('user_uploads')->put('expired-upload.pdf', 'expired');
        Storage::disk('user_uploads')->put('recent-upload.pdf', 'recent');
        Storage::disk('user_flight_releases')->put('expired-release.pdf', 'expired');
        Storage::disk('user_flight_releases')->put('recent-release.pdf', 'recent');

        touch(Storage::disk('user_uploads')->path('expired-upload.pdf'), now()->subDays(6)->timestamp);
        touch(Storage::disk('user_uploads')->path('recent-upload.pdf'), now()->subDays(2)->timestamp);
        touch(Storage::disk('user_flight_releases')->path('expired-release.pdf'), now()->subDays(6)->timestamp);
        touch(Storage::disk('user_flight_releases')->path('recent-release.pdf'), now()->subDays(2)->timestamp);

        $this->artisan('app:cleanup-user-uploads')
            ->expectsOutput('Deleted [user_uploads]: expired-upload.pdf')
            ->expectsOutput('Deleted [user_flight_releases]: expired-release.pdf')
            ->assertSuccessful();

        Storage::disk('user_uploads')->assertMissing('expired-upload.pdf');
        Storage::disk('user_uploads')->assertExists('recent-upload.pdf');
        Storage::disk('user_flight_releases')->assertMissing('expired-release.pdf');
        Storage::disk('user_flight_releases')->assertExists('recent-release.pdf');
    }

    public function test_cleanup_command_keeps_files_modified_within_the_retention_window(): void
    {
        Carbon::setTestNow('2026-07-13 12:00:00');

        Storage::fake('user_uploads');
        Storage::fake('user_flight_releases');

        Storage::disk('user_uploads')->put('retained-upload.pdf', 'recent');
        Storage::disk('user_flight_releases')->put('retained-release.pdf', 'recent');

        touch(Storage::disk('user_uploads')->path('retained-upload.pdf'), now()->subDays(5)->timestamp);
        touch(Storage::disk('user_flight_releases')->path('retained-release.pdf'), now()->subDays(4)->timestamp);

        $this->artisan('app:cleanup-user-uploads')->assertSuccessful();

        Storage::disk('user_uploads')->assertExists('retained-upload.pdf');
        Storage::disk('user_flight_releases')->assertExists('retained-release.pdf');
    }
}
