<?php

namespace Tests\Feature;

use App\Exceptions\BidPortalException;
use App\Services\CrewBids\BidPortalHttpClient;
use App\Services\CrewBids\DiscoverBidPortal;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DiscoverBidPortalTest extends TestCase
{
    private const string ORIGIN = 'https://crewbids.kalittaair.com';

    public function test_visits_the_login_landing_page_before_bid_sheet_with_the_same_session(): void
    {
        $http = $this->transport();
        $login = str_replace('action="./"', 'action="pages/Login.aspx"', file_get_contents(base_path('tests/Fixtures/crew-bids/portal-login.html')));
        $requests = [];
        $http->fake([
            self::ORIGIN.'/' => $http->response($login, 200, ['Set-Cookie' => 'session=synthetic-login; Path=/; Secure']),
            self::ORIGIN.'/pages/Login.aspx' => function (Request $request) use (&$requests, $http) {
                $requests[] = ['login', $request->header('Cookie')];

                return $http->response('', 302, ['Location' => '/Redirect.aspx', 'Set-Cookie' => 'auth=synthetic-auth; Path=/; Secure']);
            },
            self::ORIGIN.'/Redirect.aspx' => function (Request $request) use (&$requests, $http) {
                $requests[] = ['landing', $request->header('Cookie')];

                return $http->response('synthetic landing page', 200, ['Set-Cookie' => 'context=synthetic-context; Path=/; Secure']);
            },
            self::ORIGIN.'/Pages/BidSheet.aspx' => function (Request $request) use (&$requests, $http) {
                $requests[] = ['sheet', $request->header('Cookie')];

                return $http->response(file_get_contents(base_path('tests/Fixtures/crew-bids/portal-bid-sheet.html')));
            },
        ]);

        $report = app(DiscoverBidPortal::class)->handle('900000', 'synthetic-password-only');

        $this->assertSame(3, $report['choice_slots']);
        $this->assertSame(['login', 'landing', 'sheet'], array_column($requests, 0));
        $this->assertStringContainsString('auth=synthetic-auth', implode('; ', $requests[1][1]));
        $this->assertStringContainsString('context=synthetic-context', implode('; ', $requests[2][1]));
        $http->assertSentCount(4);
        $http->assertNotSent(fn (Request $request): bool => $request->method() === 'POST' && $request->url() !== self::ORIGIN.'/pages/Login.aspx');
    }

    #[DataProvider('observedLoginLocations')]
    public function test_supports_the_observed_login_action_and_redirects(string $loginLocation, string $action, int $expectedRequests): void
    {
        $http = $this->transport();
        $login = str_replace('action="./"', 'action="'.$action.'"', file_get_contents(base_path('tests/Fixtures/crew-bids/portal-login.html')));
        $sheet = file_get_contents(base_path('tests/Fixtures/crew-bids/portal-bid-sheet.html'));
        $loginPage = $loginLocation === '/' ? self::ORIGIN.'/' : self::ORIGIN.$loginLocation;
        $submitUrl = $loginLocation === '/' ? self::ORIGIN.'/pages/Login.aspx' : $loginPage;
        $http->fake([
            self::ORIGIN.'/' => $loginLocation === '/' ? $http->response($login) : $http->response('', 302, ['Location' => $loginLocation]),
            $submitUrl => $loginLocation === '/' ? $http->response('', 302, ['Location' => '/Pages/BidSheet.aspx'])
                : $http->sequence()->push($login)->push('', 302, ['Location' => '/Pages/BidSheet.aspx']),
            self::ORIGIN.'/Pages/BidSheet.aspx' => $http->response($sheet),
        ]);

        $report = app(DiscoverBidPortal::class)->handle('900000', 'synthetic-password-only');

        $this->assertSame(3, $report['choice_slots']);
        $http->assertSentCount($expectedRequests);
        $http->assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request->url() === $submitUrl
            && $request['Login1$UserName'] === '900000' && $request['Login1$Password'] === 'synthetic-password-only'
            && $request['__VIEWSTATE'] === 'synthetic-fresh-state');
        $http->assertNotSent(fn (Request $request): bool => $request->method() === 'POST' && $request->url() !== $submitUrl);
    }

    public static function observedLoginLocations(): array
    {
        return [
            'public root form' => ['/', 'pages/Login.aspx', 3],
            'redirected login form' => ['/Pages/Login.aspx?ReturnUrl=%2fPages%2fBidSheet.aspx', './Login.aspx?ReturnUrl=%2fPages%2fBidSheet.aspx', 4],
        ];
    }

    public function test_logs_in_with_fresh_state_and_returns_only_structural_metadata_without_posting_a_bid(): void
    {
        Event::fake([RequestSending::class, ResponseReceived::class, ConnectionFailed::class]);
        $http = $this->transport();
        $login = file_get_contents(base_path('tests/Fixtures/crew-bids/portal-login.html'));
        $sheet = file_get_contents(base_path('tests/Fixtures/crew-bids/portal-bid-sheet.html'));
        $http->fake([
            self::ORIGIN.'/' => $http->sequence()->push($login, 200, ['Set-Cookie' => 'session=synthetic-cookie; Path=/; Secure'])
                ->push('', 302, ['Location' => '/Pages/UnfetchedHome.aspx']),
            self::ORIGIN.'/Pages/BidSheet.aspx' => $http->response($sheet),
        ]);

        $report = app(DiscoverBidPortal::class)->handle('900000', 'synthetic-password-only', ['F1', 'Q99', 'R70']);

        $this->assertSame(['choice_slots' => 3, 'editable_slots' => 0, 'populated_slots' => 3,
            'control_names' => [], 'submitted_report_link' => true, 'candidate_choices' => 3], $report);
        $http->assertSentCount(3);
        $http->assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request->url() === self::ORIGIN.'/'
            && $request->data() === [
                '__VIEWSTATE' => 'synthetic-fresh-state', '__VIEWSTATE1' => 'synthetic-split-state',
                '__EVENTVALIDATION' => 'synthetic-validation', '__EVENTTARGET' => '', '__EVENTARGUMENT' => '',
                'Login1$LoginButton' => 'Log In', 'Login1$UserName' => '900000', 'Login1$Password' => 'synthetic-password-only',
            ] && $request->hasHeader('Cookie', 'session=synthetic-cookie'));
        $http->assertNotSent(fn (Request $request): bool => $request->method() === 'POST' && $request->url() !== self::ORIGIN.'/');
        Event::assertNothingDispatched();
    }

    #[DataProvider('unsafeActions')]
    public function test_refuses_unsafe_login_destinations_before_sending_credentials(string $action): void
    {
        $http = $this->transport();
        $login = str_replace('action="./"', 'action="'.$action.'"', file_get_contents(base_path('tests/Fixtures/crew-bids/portal-login.html')));
        $http->fake([self::ORIGIN.'/' => $http->response($login)]);

        try {
            app(DiscoverBidPortal::class)->handle('900000', 'synthetic-password-only');
            $this->fail('An unsafe action was accepted.');
        } catch (BidPortalException) {
            $http->assertSentCount(1);
        }
    }

    public static function unsafeActions(): array
    {
        return [
            'external' => ['https://example.com/login'],
            'protocol relative' => ['//example.com/login'],
            'downgrade' => ['http://crewbids.kalittaair.com/'],
            'port' => ['https://crewbids.kalittaair.com:444/'],
            'userinfo' => ['https://user@crewbids.kalittaair.com/'],
            'bid write destination' => ['/Pages/BidSheet.aspx'],
        ];
    }

    public function test_stops_an_external_login_redirect_without_forwarding_secrets(): void
    {
        $http = $this->transport();
        $http->fake([self::ORIGIN.'/' => $http->response('', 302, ['Location' => 'https://example.com/'])]);

        try {
            app(DiscoverBidPortal::class)->handle('900000', 'synthetic-password-only');
            $this->fail('An external redirect was accepted.');
        } catch (BidPortalException $exception) {
            $this->assertSame('The portal supplied an unexpected destination.', $exception->getMessage());
            $http->assertSentCount(1);
        }
    }

    public function test_detects_rejected_authentication_from_the_bid_sheet_redirect(): void
    {
        $http = $this->transport();
        $login = file_get_contents(base_path('tests/Fixtures/crew-bids/portal-login.html'));
        $http->fake([
            self::ORIGIN.'/' => $http->response($login),
            self::ORIGIN.'/Pages/BidSheet.aspx' => $http->response('', 302, ['Location' => '/']),
        ]);

        $this->expectException(BidPortalException::class);
        $this->expectExceptionMessage('Portal login was not accepted');
        app(DiscoverBidPortal::class)->handle('900000', 'synthetic-password-only');
    }

    public function test_redacts_transport_exceptions_and_does_not_retry(): void
    {
        $http = $this->transport();
        $calls = 0;
        $http->fake([self::ORIGIN.'/' => function () use (&$calls): never {
            $calls++;
            throw new ConnectionException('900000 synthetic-password-only synthetic-cookie');
        }]);

        try {
            app(DiscoverBidPortal::class)->handle('900000', 'synthetic-password-only');
            $this->fail('A transport exception was accepted.');
        } catch (BidPortalException $exception) {
            $this->assertSame('Portal discovery failed at login page request [ConnectionException]. No bid was sent.', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
            $this->assertSame(1, $calls);
        }
    }

    #[DataProvider('discoveryFailures')]
    public function test_identifies_the_failed_step_and_error_without_exposing_secrets(string $failurePoint, string $errorType): void
    {
        $http = $this->transport();
        $login = file_get_contents(base_path('tests/Fixtures/crew-bids/portal-login.html'));
        $jars = [];
        $fail = function (Request $request, array $options) use ($errorType, &$jars): never {
            $jars[] = $options['cookies'];
            $secretMessage = 'cURL error 28: 900000 synthetic-password-only synthetic-cookie private-state https://example.com/?secret=value';
            throw match ($errorType) {
                'ConnectionException' => new ConnectionException($secretMessage),
                default => new \TypeError($secretMessage),
            };
        };
        $http->fake([
            self::ORIGIN.'/' => function (Request $request, array $options) use ($failurePoint, $fail, $http, $login) {
                if ($request->method() === 'POST' && $failurePoint === 'login request') {
                    return $fail($request, $options);
                }

                return $http->response($login, 200, ['Set-Cookie' => 'session=synthetic-cookie; Path=/; Secure']);
            },
            self::ORIGIN.'/Pages/BidSheet.aspx' => $fail,
        ]);

        try {
            app(DiscoverBidPortal::class)->handle('900000', 'synthetic-password-only');
            $this->fail('A failed discovery was accepted.');
        } catch (BidPortalException $exception) {
            $this->assertStringContainsString('failed at '.$failurePoint.' ['.$errorType, $exception->getMessage());
            $this->assertStringContainsString($errorType === 'ConnectionException' ? 'cURL 28' : 'DiscoverBidPortalTest.php:', $exception->getMessage());
            foreach (['900000', 'synthetic-password-only', 'synthetic-cookie', 'private-state', 'https://', base_path()] as $secret) {
                $this->assertStringNotContainsString($secret, $exception->getMessage());
            }
            $this->assertNull($exception->getPrevious());
            $this->assertCount(1, $jars);
            $this->assertSame([], $jars[0]->toArray());
            $http->assertSentCount($failurePoint === 'login request' ? 1 : 2);
        }
    }

    public static function discoveryFailures(): array
    {
        return [
            'login timeout' => ['login request', 'ConnectionException'],
            'bid sheet timeout' => ['bid sheet request', 'ConnectionException'],
            'unexpected runtime failure' => ['bid sheet request', 'TypeError'],
        ];
    }

    public function test_discards_cookies_between_operations_even_with_a_reused_service(): void
    {
        $http = $this->transport();
        $cookieJars = [];
        $login = file_get_contents(base_path('tests/Fixtures/crew-bids/portal-login.html'));
        $sheet = file_get_contents(base_path('tests/Fixtures/crew-bids/portal-bid-sheet.html'));
        $http->fake([
            self::ORIGIN.'/' => function (Request $request, array $options) use (&$cookieJars, $http, $login) {
                $cookieJars[] = $options['cookies'];

                return $http->response($login, 200, ['Set-Cookie' => 'session=synthetic-cookie; Path=/; Secure']);
            },
            self::ORIGIN.'/Pages/BidSheet.aspx' => $http->response($sheet),
        ]);
        $service = app(DiscoverBidPortal::class);

        $service->handle('900000', 'synthetic-password-only');
        $service->handle('900001', 'another-synthetic-password');

        $this->assertSame($cookieJars[0], $cookieJars[1]);
        $this->assertNotSame($cookieJars[0], $cookieJars[2]);
        foreach ($cookieJars as $jar) {
            $this->assertSame([], $jar->toArray());
        }
        $http->assertSentCount(6);
    }

    public function test_global_http_middleware_cannot_observe_portal_secrets(): void
    {
        $http = $this->transport();
        $observed = false;
        Http::globalRequestMiddleware(function ($request) use (&$observed) {
            $observed = true;

            return $request;
        });
        $http->fake([
            self::ORIGIN.'/' => $http->response(file_get_contents(base_path('tests/Fixtures/crew-bids/portal-login.html'))),
            self::ORIGIN.'/Pages/BidSheet.aspx' => $http->response(file_get_contents(base_path('tests/Fixtures/crew-bids/portal-bid-sheet.html'))),
        ]);

        app(DiscoverBidPortal::class)->handle('900000', 'synthetic-password-only');

        $this->assertFalse($observed);
        $http->assertSentCount(3);
    }

    private function transport(): BidPortalHttpClient
    {
        Http::preventStrayRequests();
        $http = new BidPortalHttpClient;
        $http->preventStrayRequests();
        $this->app->instance(BidPortalHttpClient::class, $http);

        return $http;
    }
}
