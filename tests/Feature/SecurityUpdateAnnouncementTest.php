<?php

namespace Tests\Feature;

use App\Mail\SecurityUpdateAnnouncement;
use Illuminate\Mail\SendQueuedMailable;
use Tests\TestCase;

class SecurityUpdateAnnouncementTest extends TestCase
{
    public function test_it_leads_with_the_security_update_and_links_to_the_product_page(): void
    {
        $message = new SecurityUpdateAnnouncement('Taylor User');

        $message->assertSeeInHtml('Hi Taylor,');
        $message->assertSeeInHtml('about eight and a half hours');
        $message->assertSeeInHtml('CVE-2026-109618 and CVE-2026-109619');
        $message->assertSeeInHtml('no evidence of a server compromise or unauthorized access');
        $message->assertSeeInHtml('You do not need to take any action');
        $message->assertSeeInHtml('Flight Plan Brief');
        $message->assertSeeInHtml(route('welcome'));
        $message->assertSeeInText('CVE-2026-109618 and CVE-2026-109619');
        $this->assertSame('K4 Parser security update and Flight Plan Brief', $message->envelope()->subject);
    }

    public function test_it_retries_failed_delivery(): void
    {
        $queuedMailable = new SendQueuedMailable(new SecurityUpdateAnnouncement('Taylor'));

        $this->assertSame(5, $queuedMailable->tries);
        $this->assertSame([60, 120, 300, 600], $queuedMailable->backoff());
    }
}
