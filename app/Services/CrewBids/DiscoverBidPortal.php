<?php

namespace App\Services\CrewBids;

use App\Exceptions\BidPortalException;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use SensitiveParameter;
use Throwable;

class DiscoverBidPortal
{
    private const string ORIGIN = 'https://crewbids.kalittaair.com';

    private const array LOGIN_PATHS = ['/', '/default.aspx', '/login.aspx', '/pages/login.aspx'];

    public function __construct(private BidPortalHttpClient $http, private ReadBidPortalPage $pages) {}

    /**
     * The only POST in this operation is login. Bid Sheet controls are never posted.
     *
     * @param  list<string>  $selections
     * @return array{choice_slots: int, editable_slots: int, populated_slots: int, control_names: list<string>, submitted_report_link: bool, candidate_choices: ?int}
     */
    public function handle(#[SensitiveParameter] string $employee, #[SensitiveParameter] string $password, #[SensitiveParameter] array $selections = []): array
    {
        $cookies = new CookieJar;
        $step = 'credential validation';
        try {
            if (trim($employee) === '' || $password === '') {
                throw new BidPortalException('Employee number and password are required.');
            }
            $step = 'login page request';
            [$loginUrl, $response] = $this->get(self::ORIGIN.'/', $cookies);
            $step = 'login form parsing';
            $form = $this->pages->login($response->body());
            $step = 'login destination validation';
            $action = $this->url($loginUrl, $form['action']);
            if (! in_array(strtolower((new Uri($action))->getPath()), self::LOGIN_PATHS, true)) {
                throw new BidPortalException('The portal login destination is not supported.');
            }
            $fields = array_replace($form['fields'], ['Login1$UserName' => $employee, 'Login1$Password' => $password]);
            $step = 'login request';
            $login = $this->request('POST', $action, $cookies, $fields);
            $step = 'login response validation';
            if (in_array($login->status(), [302, 303], true)) {
                $landingUrl = $this->url($action, $login->header('Location'));
                if (strtolower((new Uri($landingUrl))->getPath()) === '/redirect.aspx') {
                    $step = 'login landing request';
                    [$landingUrl] = $this->get($landingUrl, $cookies);
                    if (in_array(strtolower((new Uri($landingUrl))->getPath()), self::LOGIN_PATHS, true)) {
                        throw new BidPortalException('Portal login was not accepted or the session expired.');
                    }
                }
            } elseif (! $login->successful()) {
                throw new BidPortalException('Portal login failed or requires an unsupported authentication step.');
            }
            $step = 'bid sheet request';
            [$sheetUrl, $response] = $this->get(self::ORIGIN.'/Pages/BidSheet.aspx', $cookies);
            if (strtolower((new Uri($sheetUrl))->getPath()) !== '/pages/bidsheet.aspx') {
                throw new BidPortalException('Portal login was not accepted or the session expired.');
            }
            $step = 'bid sheet parsing';
            $sheet = $this->pages->bidSheet($response->body());
            $step = 'bid sheet destination validation';
            if ($this->url($sheetUrl, $sheet['action']) !== self::ORIGIN.'/Pages/BidSheet.aspx') {
                throw new BidPortalException('The Bid Sheet form destination is not supported.');
            }
            if ($selections !== []) {
                $step = 'candidate validation';
                $this->pages->replacement($sheet, $selections);
            }
            $step = 'discovery summary';
            $controls = array_values(array_filter($sheet['controls'], fn (string $name): bool => preg_match('/\Actl00\$ContentPlaceHolder1\$[A-Za-z][A-Za-z0-9_]{0,60}\z/', $name) === 1));

            return ['choice_slots' => count($sheet['slots']),
                'editable_slots' => count(array_filter($sheet['slots'], fn (array $slot): bool => $slot['editable'])),
                'populated_slots' => count(array_filter($sheet['slots'], fn (array $slot): bool => $slot['line'] !== null)),
                'control_names' => $controls, 'submitted_report_link' => $sheet['submitted_report'],
                'candidate_choices' => $selections === [] ? null : count($selections)];
        } catch (BidPortalException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw $this->failure($exception, $step);
        } finally {
            $cookies->clear();
            unset($employee, $password, $fields, $form, $login, $response, $sheet);
        }
    }

    /**
     * Report only structural diagnostics; upstream messages may contain credentials, URLs, or page data.
     */
    private function failure(#[SensitiveParameter] Throwable $exception, string $step): BidPortalException
    {
        $type = class_basename($exception);
        $type = preg_match('/\A[A-Za-z][A-Za-z0-9_]*\z/', $type) === 1 ? $type : 'Throwable';
        $details = [$type];
        if ($exception instanceof ConnectionException) {
            if (preg_match('/\AcURL error ([1-9]\d{0,2}):/', $exception->getMessage(), $match) === 1) {
                $details[] = 'cURL '.(int) $match[1];
            }
        } else {
            $file = basename($exception->getFile());
            if (preg_match('/\A[A-Za-z0-9_.-]+\.php\z/', $file) === 1) {
                $details[] = $file.':'.$exception->getLine();
            }
        }

        return new BidPortalException('Portal discovery failed at '.$step.' ['.implode(', ', $details).']. No bid was sent.');
    }

    /** @return array{string, Response} */
    private function get(string $url, #[SensitiveParameter] CookieJar $cookies): array
    {
        for ($redirects = 0; $redirects <= 5; $redirects++) {
            $response = $this->request('GET', $url, $cookies);
            if ($response->successful()) {
                return [$url, $response];
            }
            if (! in_array($response->status(), [301, 302, 303, 307, 308], true) || $response->header('Location') === '') {
                throw new BidPortalException('The portal page could not be read.');
            }
            $url = $this->url($url, $response->header('Location'));
            $path = strtolower((new Uri($url))->getPath());
            if (! in_array($path, [...self::LOGIN_PATHS, '/redirect.aspx', '/pages/bidsheet.aspx'], true)) {
                throw new BidPortalException('The portal redirected to an unsupported page.');
            }
        }

        throw new BidPortalException('The portal exceeded the redirect limit.');
    }

    private function url(string $base, #[SensitiveParameter] string $reference): string
    {
        $uri = UriResolver::resolve(new Uri($base), new Uri($reference));
        if ($uri->getScheme() !== 'https' || $uri->getHost() !== 'crewbids.kalittaair.com'
            || $uri->getPort() !== null || $uri->getUserInfo() !== '' || $uri->getFragment() !== '') {
            throw new BidPortalException('The portal supplied an unexpected destination.');
        }

        return (string) $uri;
    }

    /** @param array<string, string> $fields */
    private function request(string $method, string $url, #[SensitiveParameter] CookieJar $cookies, #[SensitiveParameter] array $fields = []): Response
    {
        return $this->http->asForm()->connectTimeout(5)->timeout(20)->withoutRedirecting()
            ->withOptions(['cookies' => $cookies, 'verify' => true])
            ->send($method, $url, $method === 'POST' ? ['form_params' => $fields] : []);
    }
}
