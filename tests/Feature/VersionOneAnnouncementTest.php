<?php

namespace Tests\Feature;

use App\Mail\VersionOneAnnouncement;
use Tests\TestCase;

class VersionOneAnnouncementTest extends TestCase
{
    public function test_it_shows_email_verification_notice_for_an_unverified_recipient(): void
    {
        (new VersionOneAnnouncement('Taylor', false))->assertSeeInHtml(
            'If you haven’t signed in recently, you may be asked to verify your email address first.',
        );
    }

    public function test_it_hides_email_verification_notice_for_a_verified_recipient(): void
    {
        (new VersionOneAnnouncement('Taylor', true))->assertDontSeeInHtml(
            'If you haven’t signed in recently, you may be asked to verify your email address first.',
        );
    }
}
