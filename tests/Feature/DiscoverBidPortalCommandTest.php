<?php

namespace Tests\Feature;

use App\Exceptions\BidPortalException;
use App\Services\CrewBids\BidPortalHttpClient;
use App\Services\CrewBids\DiscoverBidPortal;
use Illuminate\Support\Facades\Exceptions;
use Tests\TestCase;

class DiscoverBidPortalCommandTest extends TestCase
{
    public function test_rejects_noninteractive_use_before_accepting_credentials(): void
    {
        $this->mock(DiscoverBidPortal::class)->shouldNotReceive('handle');

        $this->artisan('crew-bids:discover-portal', ['--no-interaction' => true])
            ->expectsOutput('Run interactively; credentials are accepted only through hidden prompts.')
            ->assertFailed();
    }

    public function test_prompts_for_hidden_credentials_and_prints_only_a_structural_summary(): void
    {
        $http = new BidPortalHttpClient;
        $http->preventStrayRequests();
        $http->fake([
            'https://crewbids.kalittaair.com/' => $http->response(file_get_contents(base_path('tests/Fixtures/crew-bids/portal-login.html'))),
            'https://crewbids.kalittaair.com/Pages/BidSheet.aspx' => $http->response(file_get_contents(base_path('tests/Fixtures/crew-bids/portal-bid-sheet.html'))),
        ]);
        $this->app->instance(BidPortalHttpClient::class, $http);

        $this->artisan('crew-bids:discover-portal', ['--lines' => 'F1,Q99,R 70'])
            ->expectsQuestion('Employee number', '900000')
            ->expectsQuestion('Portal password', 'synthetic-password-only')
            ->expectsOutputToContain('"candidate_choices": 3')
            ->doesntExpectOutputToContain('synthetic-password-only')
            ->doesntExpectOutputToContain('900000')
            ->doesntExpectOutputToContain('synthetic-private-sheet-state')
            ->doesntExpectOutputToContain('Synthetic private account')
            ->assertSuccessful();
        $http->assertSentCount(3);
    }

    public function test_reports_a_safe_failure_without_logging_portal_exceptions(): void
    {
        Exceptions::fake();
        $this->mock(DiscoverBidPortal::class)->shouldReceive('handle')->once()
            ->with('900000', 'synthetic-password-only', [])->andThrow(new BidPortalException('The portal form is not supported.'));

        $this->artisan('crew-bids:discover-portal')
            ->expectsQuestion('Employee number', '900000')
            ->expectsQuestion('Portal password', 'synthetic-password-only')
            ->expectsOutput('The portal form is not supported.')
            ->assertFailed();
        Exceptions::assertNothingReported();
    }
}
