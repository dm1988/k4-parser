<?php

namespace Tests\Fixtures;

use App\Services\CrewBids\BidPortalHttpClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;

class SharedBidPortal
{
    public static function fake(string $scenario = '', string $name = 'Example, Avery'): BidPortalHttpClient
    {
        $http = new BidPortalHttpClient;
        $http->preventStrayRequests();
        $fixture = static fn (string $file): string => str_replace('Example, Avery', htmlspecialchars($name, ENT_QUOTES, 'UTF-8'), file_get_contents(base_path('tests/Fixtures/crew-bids/'.$file)));
        $directory = $fixture('portal-employees.html');
        $sheet = $fixture('portal-open-bid-sheet.html');
        $base = '/Reserved.ReportViewerWebControl.axd?Mode=true&ReportID=synthetic-global&ControlID=synthetic-control&ReportStack=1&OpType=';
        $controls = '<script>var report="'.$base.'ReportArea&Controller=ContentPlaceHolder1_ReportViewer1";var export="'.$base.'Export&FileName=ViewAllBids&Format=";</script><select id="ContentPlaceHolder1_ReportViewer1_ctl01_ctl05_ctl00"><option value="PDF">PDF</option><option value="Excel">Excel</option></select>';
        $seat = 'FO';
        $reads = 0;
        $http->fake([
            'https://crewbids.kalittaair.com/' => static function (Request $request) use ($http, $fixture, $scenario) {
                return $request->method() === 'POST' && $scenario === 'rejected-login'
                    ? $http->response('Login rejected', 401)
                    : $http->response($fixture('portal-login.html'));
            },
            'https://crewbids.kalittaair.com/Pages/BidSheet.aspx' => static function () use ($http, $sheet, $fixture, $scenario, &$reads) {
                $reads++;

                return $http->response(match ($scenario) {
                    'wrong-context' => str_replace('12/2026', '11/2026', $sheet),
                    'expired-login' => $fixture('portal-login.html'),
                    'context-changed' => $reads > 1 ? str_replace('<td>4000</td>', '<td>4001</td>', $sheet) : $sheet,
                    'read-only-sheet' => preg_replace('/<input[^>]*name="ctl00\$ContentPlaceHolder1\$(?:hf_[^"]+|tb_[^"]+)"[^>]*>/', '', $sheet),
                    default => $sheet,
                });
            },
            'https://crewbids.kalittaair.com/Pages/ViewAllBids.aspx' => static function (Request $request) use ($http, $directory, $controls, $scenario, &$seat) {
                if ($scenario === 'connection') {
                    throw new ConnectionException('private sensitive upstream detail');
                }
                $html = $directory;
                if (($request['__EVENTTARGET'] ?? '') === 'ctl00$ContentPlaceHolder1$RadioButtonList1$0') {
                    $seat = 'CA';
                }
                if (($request['__EVENTTARGET'] ?? '') === 'ctl00$ContentPlaceHolder1$RadioButtonList1$1') {
                    $seat = 'FO';
                }
                if ($seat === 'CA') {
                    $html = str_replace(['value="FO" checked', 'value="CA" onclick', 'directory-state'], ['value="FO" onclick="javascript:setTimeout(\'__doPostBack(\\\'ctl00$ContentPlaceHolder1$RadioButtonList1$1\\\',\\\'\\\')\', 0)"', 'value="CA" checked onclick', 'ca-state'], $html);
                }
                if (($request['ctl00$ContentPlaceHolder1$ddl_crewMembers'] ?? '') === 'View All') {
                    $html = str_replace(['value="Select Name" selected', 'value="View All"', '<!--REPORT-->'], ['value="Select Name"', 'value="View All" selected', $controls], $html);
                    $html = match ($scenario) {
                        'wrong-selection' => str_replace('value="View All" selected', 'value="View All"', $html),
                        'duplicate-seniority' => str_replace('20 - Sample', '10 - Sample', $html),
                        'duplicate-name' => str_replace('Sample, Morgan', 'Example, Avery', $html),
                        'bad-export-session' => str_replace('OpType=Export', 'OpType=Export&ControlID=another-control', $html),
                        'separate-render-sessions' => str_replace('OpType=Export', 'OpType=Export&ReportID=synthetic-export-session', $html),
                        default => $html,
                    };
                }
                if (($request['ctl00$ContentPlaceHolder1$ddl_crewMembers'] ?? '') === '910001') {
                    $html = str_replace(['value="Select Name" selected', 'value="910001"', '<!--REPORT-->'], ['value="Select Name"', 'value="910001" selected', str_replace('synthetic-global', 'synthetic-individual', $controls)], $html);
                    if ($scenario === 'gap-unconfirmed') {
                        $html = str_replace('value="910001" selected', 'value="910001"', $html);
                    }
                    if ($scenario === 'gap-directory-changed') {
                        $html = str_replace('20 - Sample', '21 - Sample', $html);
                    }
                }

                return $http->response($html);
            },
            'https://crewbids.kalittaair.com/Reserved.ReportViewerWebControl.axd?*' => static function (Request $request) use ($http, $scenario, $name) {
                if (str_contains($request->url(), 'OpType=Export')) {
                    $file = str_ends_with($request->url(), 'Format=PDF') ? '%PDF-synthetic-global-export' : "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1synthetic-global-export";

                    return $http->response($scenario === 'invalid-export' ? '<html>Login</html>' : $file);
                }
                $second = str_contains($request->url(), 'PageNumber=2');
                $header = '<div id="oReportDiv"><table><tr><td>Current Bid Status For</td></tr></table><table id="t_3"><tbody><tr><td></td><td></td><td></td></tr><tr><td>Name</td><td>Choice</td><td>Line</td></tr>';
                $person = '<tr><td>'.htmlspecialchars($name, ENT_QUOTES, 'UTF-8').'</td><td></td><td></td></tr>';
                if (str_contains($request->url(), 'ReportID=synthetic-individual')) {
                    $choices = match ($scenario) {
                        'gap-recovered', 'gap-paginated', 'gap-pagination-changed', 'gap-duplicate-rank' => [1 => 2, 2 => 74, 3 => 1],
                        'gap-confirmed' => [1 => 2, 3 => 1],
                        'gap-start' => [2 => 2, 3 => 1],
                        'gap-first-recovered' => [1 => 74, 2 => 2, 3 => 1],
                        'gap-extra-sparse' => [1 => 2, 3 => 1, 5 => 74],
                        'gap-repeated-line' => [1 => 2, 3 => 2],
                        default => [1 => 2, 2 => 1, 3 => 74],
                    };
                    $paginated = in_array($scenario, ['gap-paginated', 'gap-pagination-changed', 'gap-duplicate-rank'], true);
                    if ($paginated) {
                        $choices = $second ? [3 => 1] : [1 => 2, 2 => 74];
                        if ($second && $scenario === 'gap-duplicate-rank') {
                            $choices[2] = 74;
                        }
                    }
                    $rows = $person;
                    foreach ($choices as $rank => $line) {
                        $rows .= '<tr><td></td><td>'.$rank.'</td><td>'.$line.'</td></tr>';
                    }
                    $pages = $paginated ? ($second && $scenario === 'gap-pagination-changed' ? 3 : 2) : 1;

                    return $http->response($header.$rows.'</tbody></table><table><tr><td>'.($second ? 2 : 1).' of '.$pages.'</td></tr></table></div>');
                }
                $rows = $second ? '<tr><td></td><td>2</td><td>1</td></tr><tr><td>Sample, Morgan</td><td></td><td></td></tr>' : $person.'<tr><td></td><td>1</td><td>2</td></tr>';
                $html = $header.$rows.'</tbody></table><table><tr><td>'.($second ? '2' : '1').' of 2</td></tr></table></div>';
                if (str_starts_with($scenario, 'gap-')) {
                    $html = $second ? str_replace('<td>2</td><td>1</td>', '<td>3</td><td>1</td>', $html) : $html;
                    if (in_array($scenario, ['gap-start', 'gap-first-recovered'], true) && ! $second) {
                        $html = str_replace('<td>1</td><td>2</td>', '<td>2</td><td>2</td>', $html);
                    }
                    if ($scenario === 'gap-repeated-line' && $second) {
                        $html = str_replace('<td>3</td><td>1</td>', '<td>3</td><td>2</td>', $html);
                    }
                }
                $html = match ($scenario) {
                    'missing-bidder' => str_replace('<tr><td>Sample, Morgan</td><td></td><td></td></tr>', '', $html),
                    'unknown-name' => str_replace('Sample, Morgan', 'Unknown, Person', $html),
                    'duplicate-rank' => $second ? str_replace('<td>2</td><td>1</td>', '<td>1</td><td>1</td>', $html) : $html,
                    'missing-rank' => $second ? str_replace('<td>2</td><td>1</td>', '<td>3</td><td>1</td>', $html) : $html,
                    'duplicate-line' => $second ? str_replace('<td>2</td><td>1</td>', '<td>2</td><td>2</td>', $html) : $html,
                    'pagination-changed' => $second ? str_replace('2 of 2', '2 of 3', $html) : $html,
                    'too-many-pages' => str_replace('1 of 2', '1 of 201', $html),
                    default => $html,
                };

                return $http->response($html);
            },
        ]);
        app()->instance(BidPortalHttpClient::class, $http);

        return $http;
    }
}
