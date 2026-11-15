<?php

namespace Tests\Feature;

use App\Exceptions\BidPortalException;
use App\Services\CrewBids\BidPortalHttpClient;
use App\Services\CrewBids\SubmitPortalBid;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SubmitPortalBidTest extends TestCase
{
    private const string ORIGIN = 'https://crewbids.kalittaair.com';

    private int $writeAttempts = 0;

    public function test_reviews_fresh_report_then_posts_once_with_fresh_state_exact_rank_order_and_preserved_settings(): void
    {
        Event::fake([RequestSending::class, ResponseReceived::class]);
        $http = $this->portal();
        $service = app(SubmitPortalBid::class);
        $review = $service->review('900000', ' synthetic-password ', ['F1', 'R2']);
        $this->assertSame([5, 6, 7], $review['before']);
        $this->assertSame([1, 2], $review['numbers']);
        $this->assertSame([1, 2], $review['added']);
        $this->assertSame([5, 6, 7], $review['removed']);
        $http->assertNotSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), 'BidSheet.aspx'));
        $called = 0;

        $result = $service->submit('900000', ' synthetic-password ', $review + ['selections' => ['F1', 'R2']], function () use (&$called): void {
            $called++;
        });

        $this->assertSame(['status' => 'matched'], $result);
        $this->assertSame(1, $called);
        $writes = $http->recorded(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), 'BidSheet.aspx'));
        $this->assertCount(1, $writes);
        $fields = $writes->values()[0][0]->data();
        $this->assertSame('state-4', $fields['__VIEWSTATE']);
        $this->assertSame('synthetic-validation', $fields['__EVENTVALIDATION']);
        $this->assertSame('ctl00$ContentPlaceHolder1$btn_update', $fields['__EVENTTARGET']);
        $this->assertSame('', $fields['__EVENTARGUMENT']);
        $this->assertSame('1', $fields['ctl00$ContentPlaceHolder1$tb_c1']);
        $this->assertSame('2', $fields['ctl00$ContentPlaceHolder1$tb_c16']);
        $this->assertSame('3', $fields['ctl00$ContentPlaceHolder1$tb_c31']);
        $this->assertSame('1', $fields['ctl00$ContentPlaceHolder1$tb_l1']);
        $this->assertSame('2', $fields['ctl00$ContentPlaceHolder1$tb_l16']);
        $this->assertSame('', $fields['ctl00$ContentPlaceHolder1$tb_l31']);
        $this->assertSame('on', $fields['ctl00$ContentPlaceHolder1$Overtime']);
        $this->assertSame('synthetic-setting', $fields['ctl00$ContentPlaceHolder1$CBT']);
        $this->assertSame('keep', $fields['ctl00$ContentPlaceHolder1$Comp']);
        $this->assertSame('synthetic note', $fields['ctl00$ContentPlaceHolder1$Notes']);
        $this->assertArrayNotHasKey('ctl00$ContentPlaceHolder1$Unchecked', $fields);
        $this->assertArrayNotHasKey('ctl00$ContentPlaceHolder1$Disabled', $fields);
        $http->assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request->url() === self::ORIGIN.'/' && $request['Login1$Password'] === ' synthetic-password ');
        Event::assertNothingDispatched();
    }

    public function test_timeout_after_send_is_unknown_and_reconciliation_never_posts_an_update(): void
    {
        $http = $this->portal(timeout: true);
        $service = app(SubmitPortalBid::class);
        $review = $service->review('900000', 'synthetic-password', ['F1', 'R2']) + ['selections' => ['F1', 'R2']];

        $result = $service->submit('900000', 'synthetic-password', $review, static function (): void {});
        $this->assertSame(['status' => 'unknown'], $result);
        $this->assertSame(['status' => 'matched'], $service->reconcile('900000', 'synthetic-password', $review));
        $this->assertSame(1, $this->writeAttempts);
    }

    public function test_the_displayed_online_user_count_can_change_between_review_and_submission(): void
    {
        $http = $this->portal(dynamicCounter: true);
        $service = app(SubmitPortalBid::class);
        $review = $service->review('900000', 'synthetic-password', ['F1', 'R2']) + ['selections' => ['F1', 'R2']];

        $result = $service->submit('900000', 'synthetic-password', $review, static function (): void {});

        $this->assertSame(['status' => 'matched'], $result);
        $writes = $http->recorded(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), 'BidSheet.aspx'))->values();
        $this->assertCount(1, $writes);
        $this->assertSame('104', $writes[0][0]['ctl00$tb_usersLoggedIn']);
    }

    #[DataProvider('changedBindings')]
    public function test_changed_portal_state_requires_another_review_without_writing(string $from, string $to): void
    {
        $html = file_get_contents(base_path('tests/Fixtures/crew-bids/portal-open-bid-sheet.html'));
        $this->portal();
        $service = app(SubmitPortalBid::class);
        $review = $service->review('900000', 'synthetic-password', ['F1', 'R2']) + ['selections' => ['F1', 'R2']];
        $http = $this->portal(sheet: str_replace($from, $to, $html));

        try {
            app(SubmitPortalBid::class)->submit('900000', 'synthetic-password', $review, static function (): void {});
            $this->fail('A changed portal binding was accepted.');
        } catch (BidPortalException) {
            $http->assertNotSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), 'BidSheet.aspx'));
        }
    }

    public static function changedBindings(): array
    {
        return [
            'closed window' => ['>Open<', '>Closed<'], 'fleet' => ['>777<', '>747<'], 'seat' => ['>FO<', '>CA<'],
            'period' => ['12/2026', '11/2026'], 'revision' => ['<td>0</td>', '<td>1</td>'],
            'package id' => ['<td>4000</td>', '<td>4001</td>'],
            'authenticated account' => ['>900000<', '>900001<'],
            'training' => ['value="False"', 'value="True"'],
            'unrelated setting' => ['synthetic-setting', 'changed-setting'],
        ];
    }

    public function test_missing_report_export_and_external_export_destinations_never_send_credentials_or_bids(): void
    {
        $http = $this->portal(report: '<script>var exportUrl="https://example.com/Reserved.ReportViewerWebControl.axd?OpType=Export";</script>');

        try {
            app(SubmitPortalBid::class)->review('900000', 'synthetic-password', ['F1']);
            $this->fail('An external export was accepted.');
        } catch (BidPortalException $exception) {
            $this->assertStringNotContainsString('synthetic-password', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
            $http->assertNotSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://example.com'));
            $http->assertNotSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), 'BidSheet.aspx'));
        }
    }

    public function test_missing_export_is_not_treated_as_an_empty_submitted_bid(): void
    {
        $http = $this->portal(report: '<html><body>Report is still loading</body></html>');

        try {
            app(SubmitPortalBid::class)->review('900000', 'synthetic-password', ['F1']);
            $this->fail('Missing report data was accepted.');
        } catch (BidPortalException $exception) {
            $this->assertStringContainsString('PDF export could not be located', $exception->getMessage());
            $http->assertNotSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), 'BidSheet.aspx'));
        }
    }

    #[DataProvider('unsafeLoginActions')]
    public function test_rejects_unexpected_login_destinations_before_sending_credentials(string $action): void
    {
        $http = new BidPortalHttpClient;
        $http->preventStrayRequests();
        $this->app->instance(BidPortalHttpClient::class, $http);
        $login = str_replace('action="./"', 'action="'.$action.'"', file_get_contents(base_path('tests/Fixtures/crew-bids/portal-login.html')));
        $http->fake([self::ORIGIN.'/' => $http->response($login)]);

        try {
            app(SubmitPortalBid::class)->review('900000', 'synthetic-password', ['F1']);
            $this->fail('An unsafe login destination was accepted.');
        } catch (BidPortalException $exception) {
            $this->assertStringNotContainsString('synthetic-password', $exception->getMessage());
            $http->assertSentCount(1);
            $http->assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
        }
    }

    public static function unsafeLoginActions(): array
    {
        return [
            'external' => ['https://example.com/login'], 'relative external' => ['//example.com/login'],
            'insecure' => ['http://crewbids.kalittaair.com/'], 'wrong write target' => ['/Pages/BidSheet.aspx'],
        ];
    }

    public function test_a_contradictory_report_after_update_remains_unknown(): void
    {
        $this->portal(afterReport: 'before');
        $service = app(SubmitPortalBid::class);
        $review = $service->review('900000', 'synthetic-password', ['F1', 'R2']) + ['selections' => ['F1', 'R2']];

        $this->assertSame(['status' => 'unknown'], $service->submit('900000', 'synthetic-password', $review, static function (): void {}));
        $this->assertSame(['status' => 'not_matched'], $service->reconcile('900000', 'synthetic-password', $review));
        $this->assertSame(1, $this->writeAttempts);
    }

    public function test_current_submitted_order_is_part_of_the_preflight_binding(): void
    {
        $this->portal();
        $review = app(SubmitPortalBid::class)->review('900000', 'synthetic-password', ['F1', 'R2']) + ['selections' => ['F1', 'R2']];
        $http = $this->portal(initialReport: 'after');

        try {
            app(SubmitPortalBid::class)->submit('900000', 'synthetic-password', $review, static function (): void {});
            $this->fail('A changed submitted order was accepted.');
        } catch (BidPortalException $exception) {
            $this->assertStringContainsString('submitted choices changed', $exception->getMessage());
            $http->assertNotSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), 'BidSheet.aspx'));
        }
    }

    private function portal(bool $timeout = false, ?string $sheet = null, ?string $report = null, string $initialReport = 'before', string $afterReport = 'after', bool $dynamicCounter = false): BidPortalHttpClient
    {
        $http = new BidPortalHttpClient;
        $http->preventStrayRequests();
        $this->app->instance(BidPortalHttpClient::class, $http);
        $written = false;
        $reads = 0;
        $html = $sheet ?? file_get_contents(base_path('tests/Fixtures/crew-bids/portal-open-bid-sheet.html'));
        $http->fake([
            self::ORIGIN.'/' => fn (Request $request) => $request->method() === 'GET'
                ? $http->response(file_get_contents(base_path('tests/Fixtures/crew-bids/portal-login.html')), 200, ['Set-Cookie' => 'session=synthetic-session; Path=/; Secure'])
                : $http->response('', 302, ['Location' => '/Pages/BidSheet.aspx']),
            self::ORIGIN.'/Pages/BidSheet.aspx' => function (Request $request) use ($http, $html, $timeout, $dynamicCounter, &$written, &$reads) {
                if ($request->method() === 'POST') {
                    $this->writeAttempts++;
                    $written = true;
                    if ($timeout) {
                        throw new ConnectionException('synthetic-password secret upstream response');
                    }

                    return $http->response('Update returned without a receipt.');
                }
                $current = $written ? str_replace(['value="5"', 'value="6"', 'value="7"'], ['value="1"', 'value="2"', 'value=""'], $html) : $html;
                if ($dynamicCounter) {
                    $current = str_replace('name="ctl00$tb_usersLoggedIn" value="12"', 'name="ctl00$tb_usersLoggedIn" value="'.(101 + $reads).'"', $current);
                }

                return $http->response(str_replace('synthetic-sheet-state', 'state-'.(++$reads), $current));
            },
            self::ORIGIN.'/Pages/BidLineDataOutput.aspx' => $http->response($report ?? file_get_contents(base_path('tests/Fixtures/crew-bids/portal-submitted-report.html'))),
            self::ORIGIN.'/Reserved.ReportViewerWebControl.axd?*' => function () use ($http, $initialReport, $afterReport, &$written) {
                return $http->response(file_get_contents(base_path('tests/Fixtures/crew-bids/submitted-'.($written ? $afterReport : $initialReport).'.pdf')), 200, ['Content-Type' => 'application/pdf']);
            },
        ]);

        return $http;
    }
}
