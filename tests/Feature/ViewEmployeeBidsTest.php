<?php

namespace Tests\Feature;

use App\Exceptions\BidPortalException;
use App\Models\BidPackage;
use App\Services\CrewBids\BidPortalHttpClient;
use App\Services\CrewBids\BidPortalSession;
use App\Services\CrewBids\ReadEmployeeBidPage;
use App\Services\CrewBids\ViewEmployeeBids;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ViewEmployeeBidsTest extends TestCase
{
    private const string ORIGIN = 'https://crewbids.kalittaair.com';

    #[DataProvider('supportedPostbacks')]
    public function test_accepts_equivalent_selector_postbacks_without_requiring_browser_serialization(string $script): void
    {
        $html = file_get_contents(base_path('tests/Fixtures/crew-bids/portal-employees.html'));
        $html = preg_replace('/onchange="[^"]*"/', 'onchange="'.htmlspecialchars($script, ENT_QUOTES, 'UTF-8').'"', $html);
        $reader = app(ReadEmployeeBidPage::class);

        $page = $reader->read($html, '900000', '777');
        $fields = $reader->select($page, 'FO', 'View All');

        $this->assertSame('ctl00$ContentPlaceHolder1$ddl_crewMembers', $fields['__EVENTTARGET']);
        $this->assertSame('', $fields['__EVENTARGUMENT']);
        $this->assertSame('View All', $fields['ctl00$ContentPlaceHolder1$ddl_crewMembers']);
    }

    public static function supportedPostbacks(): array
    {
        $target = 'ctl00$ContentPlaceHolder1$ddl_crewMembers';

        return [
            ['__doPostBack("'.$target.'","")'],
            ["javascript:__doPostBack('".$target."','');"],
            ["javascript:setTimeout('__doPostBack(\\'".$target."\\',\\'\\')',0);"],
            ['setTimeout("__doPostBack(\\"'.$target.'\\",\\"\\")", 0);'],
            ["javascript: setTimeout( '__doPostBack(\\'".$target."\\', \\'\\')' , 0 ) ;"],
        ];
    }

    #[DataProvider('unsafePostbacks')]
    public function test_selector_scripts_never_supply_postback_targets_arguments_or_extra_actions(string $script): void
    {
        $html = file_get_contents(base_path('tests/Fixtures/crew-bids/portal-employees.html'));
        $html = preg_replace('/onchange="[^"]*"/', 'onchange="'.htmlspecialchars($script, ENT_QUOTES, 'UTF-8').'"', $html);
        $reader = app(ReadEmployeeBidPage::class);

        $fields = $reader->select($reader->read($html, '900000', '777'), 'FO', 'View All');

        $this->assertSame('ctl00$ContentPlaceHolder1$ddl_crewMembers', $fields['__EVENTTARGET']);
        $this->assertSame('', $fields['__EVENTARGUMENT']);
    }

    public static function unsafePostbacks(): array
    {
        $target = 'ctl00$ContentPlaceHolder1$ddl_crewMembers';

        return [
            ["__doPostBack('ctl00\$ContentPlaceHolder1\$btn_update','')"],
            ["__doPostBack('".$target."','Update')"],
            ["__doPostBack('".$target."','');submitBid()"],
            ["setTimeout('__doPostBack(\\'".$target."\\',\\'\\')',1000)"],
        ];
    }

    public function test_reads_selected_employee_in_original_order_with_unique_local_labels_and_no_bid_update(): void
    {
        $this->freezeTime();
        Event::fake([RequestSending::class, ResponseReceived::class]);
        $http = $this->portal();

        $result = $this->read();

        $this->assertSame('910001', $result['selected']);
        $this->assertSame([
            ['rank' => 1, 'number' => 2, 'local_id' => 'R2'],
            ['rank' => 2, 'number' => 1, 'local_id' => 'F1'],
            ['rank' => 3, 'number' => 74, 'local_id' => null],
        ], $result['choices']);
        $this->assertSame(['PDF', 'Excel'], $result['formats']);
        $this->assertSame(now()->utc()->format('Y-m-d H:i:s').' UTC', $result['checked_at']);
        $posts = $http->recorded(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), 'ViewAllBids.aspx'));
        $this->assertCount(1, $posts);
        $fields = $posts->values()[0][0]->data();
        $this->assertSame('directory-state', $fields['__VIEWSTATE']);
        $this->assertSame('ctl00$ContentPlaceHolder1$ddl_crewMembers', $fields['__EVENTTARGET']);
        $this->assertSame('910001', $fields['ctl00$ContentPlaceHolder1$ddl_crewMembers']);
        $http->assertNotSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), 'BidSheet.aspx'));
        Event::assertNothingDispatched();
        $encoded = json_encode($result);
        foreach (['synthetic-password', 'directory-state', 'ReportID', 'ControlID'] as $secret) {
            $this->assertStringNotContainsString($secret, $encoded);
        }
    }

    public function test_position_switch_refreshes_directory_and_uses_fresh_postback_state(): void
    {
        $http = $this->portal();

        $result = $this->read(seat: 'CA');

        $this->assertSame('CA', $result['seat']);
        $posts = $http->recorded(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), 'ViewAllBids.aspx'))->values();
        $this->assertCount(2, $posts);
        $this->assertSame('ctl00$ContentPlaceHolder1$RadioButtonList1$0', $posts[0][0]['__EVENTTARGET']);
        $this->assertSame('ca-state', $posts[1][0]['__VIEWSTATE']);
        $this->assertSame('CA', $posts[1][0]['ctl00$ContentPlaceHolder1$RadioButtonList1']);
    }

    public function test_directory_only_read_returns_no_report_or_export_and_skips_view_all(): void
    {
        $http = $this->portal();

        $result = $this->read(selected: null);

        $this->assertSame([], $result['choices']);
        $this->assertSame([], $result['formats']);
        $this->assertSame([910001, 910002], array_keys($result['employees']));
        $http->assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'ReportViewerWebControl'));
    }

    #[DataProvider('exports')]
    public function test_exports_only_the_fresh_selected_report(string $format, string $signature): void
    {
        $http = $this->portal(export: $signature.'synthetic-document');

        $result = $this->read(format: $format);

        $this->assertSame($signature.'synthetic-document', $result['download']);
        $http->assertSent(fn (Request $request): bool => str_contains($request->url(), 'OpType=Export') && str_ends_with($request->url(), 'Format='.$format));
    }

    public static function exports(): array
    {
        return [['PDF', '%PDF-'], ['Excel', "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1"]];
    }

    public function test_unknown_or_stale_employee_is_rejected_before_employee_postback(): void
    {
        $http = $this->portal();
        try {
            $this->read(selected: '999999');
            $this->fail('An unknown employee was accepted.');
        } catch (BidPortalException $exception) {
            $this->assertStringContainsString('fresh portal directory', $exception->getMessage());
        }
        $http->assertNotSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), 'ViewAllBids.aspx'));
    }

    public function test_collects_all_report_pages_before_returning_choices(): void
    {
        $http = $this->portal(scenario: 'two-pages');

        $result = $this->read();

        $this->assertSame([2, 1, 74], array_column($result['choices'], 'number'));
        $http->assertSent(fn (Request $request): bool => str_contains($request->url(), 'PageNumber=2'));
    }

    public function test_empty_directory_is_distinct_from_a_confirmed_empty_bid(): void
    {
        $http = $this->portal(scenario: 'empty-directory');

        $result = $this->read(selected: null);

        $this->assertSame([], $result['employees']);
        $this->assertNull($result['selected']);
        $http->assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'OpType=ReportArea'));
    }

    #[DataProvider('invalidResponses')]
    public function test_rejects_unverified_report_selection_context_and_downloads(string $scenario, string $message): void
    {
        $http = $this->portal(scenario: $scenario);
        $this->expectException(BidPortalException::class);
        $this->expectExceptionMessage($message);
        try {
            $this->read(format: $scenario === 'bad-export' || $scenario === 'unsupported-export' ? 'PDF' : null);
        } finally {
            $http->assertNotSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), 'BidSheet.aspx'));
        }
    }

    public static function invalidResponses(): array
    {
        return [
            ['wrong-name', 'identity or choice layout'], ['wrong-selection', 'selected employee report'],
            ['wrong-seat', 'selected employee report'], ['missing-report', 'selected employee report'],
            ['missing-rank', 'missing choice ranks'], ['duplicate-line', 'duplicate choices'], ['duplicate-rank', 'identity or choice layout'],
            ['wrong-month', 'month or fleet'], ['context-changed', 'context changed'],
            ['unsafe-report', 'selected employee report'], ['wrong-export-session', 'unverified report'],
            ['unsupported-export', 'format is unavailable'], ['bad-export', 'supported report file'],
            ['redirect-report', 'redirected or the portal session expired'],
            ['expired', 'layout is missing or ambiguous'], ['oversized', 'response is too large'],
            ['timeout', 'operation could not be completed'],
            ['wrong-fleet', 'month or fleet'], ['wrong-account', 'month or fleet'],
            ['duplicate-employee', 'directory layout is unsupported'], ['unsafe-postback', 'selector is unsupported'],
        ];
    }

    public function test_ambiguous_local_numbers_remain_unmapped(): void
    {
        $http = $this->portal();
        $package = $this->package();
        $payload = $package->payload;
        $payload['lines']['Q1'] = array_replace($payload['lines']['F1'], ['id' => 'Q1']);
        $package->payload = $payload;

        $result = app(ViewEmployeeBids::class)->read($package, '900000', 'synthetic-password', 'FO', '910001');

        $this->assertNull($result['choices'][1]['local_id']);
        $http->assertNotSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), 'BidSheet.aspx'));
    }

    public function test_employee_transport_rejects_bid_update_fields(): void
    {
        $http = $this->portal();
        try {
            app(BidPortalSession::class)->viewEmployees('900000', 'synthetic-password', function ($sheet, $get, $post): array {
                $post(['__EVENTTARGET' => 'ctl00$ContentPlaceHolder1$btn_update']);

                return [];
            });
            $this->fail('A bid update target was accepted.');
        } catch (BidPortalException $exception) {
            $this->assertStringContainsString('postback is not supported', $exception->getMessage());
        }
        $http->assertNotSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), '/Pages/'));
    }

    private function read(string $seat = 'FO', ?string $selected = '910001', ?string $format = null): array
    {
        return app(ViewEmployeeBids::class)->read($this->package(), '900000', 'synthetic-password', $seat, $selected, $format);
    }

    private function package(): BidPackage
    {
        return BidPackage::factory()->make(['imported_by' => 1, 'period' => '2026-12']);
    }

    private function portal(string $scenario = '', string $export = '%PDF-synthetic'): BidPortalHttpClient
    {
        $http = new BidPortalHttpClient;
        $http->preventStrayRequests();
        $fixture = fn (string $name): string => file_get_contents(base_path('tests/Fixtures/crew-bids/'.$name));
        $sheet = $fixture('portal-open-bid-sheet.html');
        $directory = $fixture('portal-employees.html');
        $report = $fixture('portal-employee-report.html');
        $seat = 'FO';
        $sheetReads = 0;
        $http->fake([
            self::ORIGIN.'/' => $http->response($fixture('portal-login.html')),
            self::ORIGIN.'/Pages/BidSheet.aspx' => function () use ($http, $sheet, $scenario, &$sheetReads) {
                $sheetReads++;

                return $http->response(match ($scenario) {
                    'wrong-month' => str_replace('12/2026', '11/2026', $sheet),
                    'wrong-fleet' => str_replace('777', '747', $sheet),
                    'wrong-account' => str_replace('900000', '800000', $sheet),
                    'context-changed' => $sheetReads > 1 ? str_replace('<td>4000</td>', '<td>4001</td>', $sheet) : $sheet,
                    default => $sheet,
                });
            },
            self::ORIGIN.'/Pages/ViewAllBids.aspx' => function (Request $request) use ($http, $directory, $scenario, &$seat) {
                if ($scenario === 'timeout') {
                    throw new ConnectionException('sensitive upstream failure');
                }
                if ($scenario === 'expired') {
                    return $http->response('<form>Login</form>');
                }
                if ($scenario === 'oversized') {
                    return $http->response(str_repeat('x', 8_000_001));
                }
                $html = $directory;
                $html = match ($scenario) {
                    'empty-directory' => preg_replace('/<option value="91000[12]">[^<]*<\/option>/', '', $html),
                    'duplicate-employee' => str_replace('value="910002"', 'value="910001"', $html),
                    'unsafe-postback' => str_replace('name="ctl00$ContentPlaceHolder1$ddl_crewMembers"', 'name="ctl00$ContentPlaceHolder1$btn_update"', $html),
                    default => $html,
                };
                $target = $request['__EVENTTARGET'] ?? '';
                if ($target === 'ctl00$ContentPlaceHolder1$RadioButtonList1$0') {
                    $seat = 'CA';
                }
                if ($seat === 'CA') {
                    $html = str_replace(['value="FO" checked', 'value="CA" onclick', 'directory-state'], ['value="FO" onclick="javascript:setTimeout(\'__doPostBack(\\\'ctl00$ContentPlaceHolder1$RadioButtonList1$1\\\',\\\'\\\')\', 0)"', 'value="CA" checked onclick', 'ca-state'], $html);
                }
                if ($target === 'ctl00$ContentPlaceHolder1$ddl_crewMembers') {
                    $html = str_replace(['value="Select Name" selected', 'value="910001"'], ['value="Select Name"', 'value="910001" selected'], $html);
                    $base = '/Reserved.ReportViewerWebControl.axd?Mode=true&ReportID=synthetic-report&ControlID=synthetic-control&ReportStack=1&OpType=';
                    $controls = '<script>var report="'.$base.'ReportArea&Controller=ContentPlaceHolder1_ReportViewer1&LinkTarget=_top"; var export="'.$base.'Export&FileName=ViewAllBids&ContentDisposition=OnlyHtmlInline&Format=";</script><select id="ContentPlaceHolder1_ReportViewer1_ctl01_ctl05_ctl00"><option value="PDF">PDF</option><option value="Excel">Excel</option></select>';
                    $html = str_replace('<!--REPORT-->', $controls, $html);
                    $html = match ($scenario) {
                        'wrong-selection' => str_replace('value="910001" selected', 'value="910001"', $html),
                        'wrong-seat' => str_replace(['value="FO" checked', 'value="CA" onclick'], ['value="FO" onclick="javascript:setTimeout(\'__doPostBack(\\\'ctl00$ContentPlaceHolder1$RadioButtonList1$1\\\',\\\'\\\')\', 0)"', 'value="CA" checked onclick'], $html),
                        'missing-report' => str_replace($controls, '', $html),
                        'unsafe-report' => str_replace('/Reserved.ReportViewerWebControl.axd?', 'https://evil.example/Reserved.ReportViewerWebControl.axd?', $html),
                        'wrong-export-session' => str_replace('OpType=Export', 'OpType=Export&ControlID=another-control', $html),
                        'unsupported-export' => str_replace('value="PDF"', 'value="CSV"', $html),
                        default => $html,
                    };
                }

                return $http->response($html);
            },
            self::ORIGIN.'/Reserved.ReportViewerWebControl.axd?*' => function (Request $request) use ($http, $report, $export, $scenario) {
                if (str_contains($request->url(), 'OpType=Export')) {
                    return $http->response($scenario === 'bad-export' ? '<html>Login</html>' : $export);
                }
                if ($scenario === 'redirect-report') {
                    return $http->response('', 302, ['Location' => '/Pages/BidLineDataOutput.aspx']);
                }
                if ($scenario === 'two-pages') {
                    $second = str_contains($request->url(), 'PageNumber=2');
                    $html = str_replace('1 of 1', $second ? '2 of 2' : '1 of 2', $report);
                    $pattern = $second ? '/<tr><td>&nbsp;<\/td><td>[12]<\/td><td>\d+<\/td><\/tr>/' : '/<tr><td>&nbsp;<\/td><td>3<\/td><td>74<\/td><\/tr>/';

                    return $http->response(preg_replace($pattern, '', $html));
                }

                return $http->response(match ($scenario) {
                    'wrong-name' => str_replace('Example, Avery', 'Another, Person', $report),
                    'missing-rank' => str_replace('<td>2</td><td>1</td>', '<td>4</td><td>1</td>', $report),
                    'duplicate-line' => str_replace('<td>3</td><td>74</td>', '<td>3</td><td>2</td>', $report),
                    'duplicate-rank' => str_replace('<td>3</td><td>74</td>', '<td>2</td><td>74</td>', $report),
                    default => $report,
                });
            },
            self::ORIGIN.'/Pages/BidLineDataOutput.aspx' => $http->response('unexpected redirect target'),
        ]);
        app()->instance(BidPortalHttpClient::class, $http);

        return $http;
    }
}
