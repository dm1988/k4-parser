<?php

namespace App\Services\CrewBids;

use App\Exceptions\BidPortalException;
use Closure;
use GuzzleHttp\Psr7\Uri;
use SensitiveParameter;
use Throwable;

class SubmitPortalBid
{
    public function __construct(private BidPortalSession $session, private ReadBidPortalPage $pages, private ReadSubmittedBidReport $reports) {}

    /** @return array{context: array<string, mixed>, numbers: list<int>} */
    public function readSubmitted(#[SensitiveParameter] string $employee, #[SensitiveParameter] string $password): array
    {
        return $this->session->run($employee, $password, function (string $html, Closure $get) use ($employee): array {
            $snapshot = $this->snapshot($html, $employee, $get, false);

            return ['context' => $snapshot['context'], 'numbers' => $snapshot['current']];
        });
    }

    /** @param list<string> $selections
     * @return array<string, mixed>
     */
    public function review(#[SensitiveParameter] string $employee, #[SensitiveParameter] string $password, array $selections): array
    {
        return $this->session->run($employee, $password, function (string $html, Closure $get) use ($employee, $selections): array {
            $snapshot = $this->snapshot($html, $employee, $get, true);
            $replacement = $this->pages->replacement($snapshot['sheet'], $selections);
            $numbers = array_map(intval(...), array_values(array_filter($replacement, fn (string $value): bool => $value !== '')));
            $before = $snapshot['current'];
            $retainedBefore = array_values(array_intersect($before, $numbers));
            $retainedAfter = array_values(array_intersect($numbers, $before));

            return ['account_hash' => $this->accountHash($employee), 'portal_hash' => $snapshot['hash'],
                'context' => $snapshot['context'], 'before' => $before, 'numbers' => $numbers,
                'added' => array_values(array_diff($numbers, $before)), 'removed' => array_values(array_diff($before, $numbers)),
                'reordered' => $retainedBefore !== $retainedAfter];
        });
    }

    /** @param array<string, mixed> $review
     * @param  Closure(): void  $beforeSend
     * @return array{status: string}
     */
    public function submit(#[SensitiveParameter] string $employee, #[SensitiveParameter] string $password, array $review, Closure $beforeSend): array
    {
        if (! hash_equals($review['account_hash'], $this->accountHash($employee))) {
            throw new BidPortalException('Use the same portal account that you reviewed.');
        }

        return $this->session->run($employee, $password, function (string $html, Closure $get, Closure $post) use ($employee, $review, $beforeSend): array {
            $snapshot = $this->snapshot($html, $employee, $get, true);
            if (! hash_equals($review['portal_hash'], $snapshot['hash'])) {
                throw new BidPortalException('The portal context, settings or submitted choices changed. Review a fresh comparison before submitting.');
            }
            $replacement = $this->pages->replacement($snapshot['sheet'], $review['selections']);
            $fields = array_replace($snapshot['fields'], $replacement);
            $beforeSend();
            try {
                $post($fields);
                $fresh = $this->snapshot($get('/Pages/BidSheet.aspx'), $employee, $get, false);

                return ['status' => $fresh['context'] === $review['context'] && $fresh['current'] === $review['numbers'] ? 'matched' : 'unknown'];
            } catch (Throwable) {
                return ['status' => 'unknown'];
            }
        });
    }

    /** @param array<string, mixed> $review
     * @return array{status: string}
     */
    public function reconcile(#[SensitiveParameter] string $employee, #[SensitiveParameter] string $password, array $review): array
    {
        if (! hash_equals($review['account_hash'], $this->accountHash($employee))) {
            throw new BidPortalException('Use the same portal account that you reviewed.');
        }

        return $this->session->run($employee, $password, function (string $html, Closure $get) use ($employee, $review): array {
            $fresh = $this->snapshot($html, $employee, $get, false);
            if ($fresh['context'] !== $review['context']) {
                throw new BidPortalException('The portal bid context changed. Check the submission directly in Kalitta.');
            }

            return ['status' => $fresh['current'] === $review['numbers'] ? 'matched' : 'not_matched'];
        });
    }

    /** @param Closure(string): string $get
     * @return array{sheet: array<string, mixed>, context: array<string, mixed>, current: list<int>, fields: array<string, string>, hash: string}
     */
    private function snapshot(#[SensitiveParameter] string $html, #[SensitiveParameter] string $employee, Closure $get, bool $writable): array
    {
        $sheet = $this->pages->bidSheet($html);
        if ($this->session->destination('https://crewbids.kalittaair.com/Pages/BidSheet.aspx', $sheet['action']) !== 'https://crewbids.kalittaair.com/Pages/BidSheet.aspx') {
            throw new BidPortalException('The Bid Sheet form destination is not supported.');
        }
        $context = $this->pages->context($html, $employee, $sheet['maximum_line']);
        if (! $context['account_matches_input'] || ! $sheet['submitted_report']) {
            throw new BidPortalException('The portal account or submitted-report link could not be verified.');
        }
        if ($writable && ($context['window_status'] !== 'Open' || ! $context['update_postback']
            || count(array_filter($sheet['slots'], fn (array $slot): bool => $slot['editable'])) !== count($sheet['slots']))) {
            throw new BidPortalException('The portal bid window is closed or the Update form is not supported.');
        }
        $current = $this->report($get);
        $afterHtml = $get('/Pages/BidSheet.aspx');
        $afterSheet = $this->pages->bidSheet($afterHtml);
        $afterContext = $this->pages->context($afterHtml, $employee, $afterSheet['maximum_line']);
        if ($context !== $afterContext || $sheet !== $afterSheet) {
            throw new BidPortalException('The portal changed while its report was being read. Start a fresh review.');
        }
        $fields = $writable ? $this->pages->updateFields($afterHtml) : [];
        $settings = $fields;
        unset($settings['ctl00$tb_usersLoggedIn']);
        foreach (array_keys($settings) as $name) {
            if (str_starts_with($name, '__')) {
                unset($settings[$name]);
            }
        }
        ksort($settings);
        $binding = $context;
        unset($binding['window_status'], $binding['update_postback']);

        return ['sheet' => $sheet, 'context' => $binding, 'current' => $current, 'fields' => $fields,
            'hash' => hash('sha256', json_encode([$context, $sheet, $current, $settings], JSON_THROW_ON_ERROR))];
    }

    /** @param Closure(string): string $get
     * @return list<int>
     */
    private function report(Closure $get): array
    {
        $html = $get('/Pages/BidLineDataOutput.aspx');
        $decoded = str_replace(['\\u0026', '\\/'], ['&', '/'], html_entity_decode($html, ENT_QUOTES | ENT_HTML5));
        preg_match_all('/[\'\"]([^\'\"<>\s]*Reserved\.ReportViewerWebControl\.axd\?[^\'\"<>\s]*)[\'\"]/i', $decoded, $matches);
        $exports = [];
        foreach ($matches[1] as $reference) {
            $url = $this->session->destination('https://crewbids.kalittaair.com/Pages/BidLineDataOutput.aspx', $reference);
            $uri = new Uri($url);
            parse_str($uri->getQuery(), $query);
            if (($query['OpType'] ?? null) === 'Export') {
                $query['Format'] = 'PDF';
                $exports[] = (string) $uri->withQuery(http_build_query($query));
            }
        }
        $exports = array_values(array_unique($exports));
        if (count($exports) !== 1) {
            throw new BidPortalException('A fresh Submitted Bid Lines PDF export could not be located. No update was sent.');
        }
        $content = $get($exports[0]);
        if (! str_starts_with($content, '%PDF-')) {
            throw new BidPortalException('The submitted report export did not return a PDF.');
        }

        return $this->reports->pdf($content);
    }

    private function accountHash(#[SensitiveParameter] string $employee): string
    {
        return hash_hmac('sha256', $employee, (string) config('app.key'));
    }
}
