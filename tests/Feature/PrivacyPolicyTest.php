<?php

namespace Tests\Feature;

use Tests\TestCase;

class PrivacyPolicyTest extends TestCase
{
    public function test_the_privacy_policy_page_is_available(): void
    {
        $response = $this->get('/privacy-policy');

        $response->assertOk();
        $response->assertSeeText('Privacy Policy');
        $response->assertSeeText('extraction service');
        $response->assertDontSeeText('parser service');
    }
}
