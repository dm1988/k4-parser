<?php

namespace App\Services\CrewBids;

use App\Exceptions\BidPortalException;
use Closure;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\Response;
use Psr\Http\Message\ResponseInterface;
use SensitiveParameter;
use Throwable;

class BidPortalSession
{
    private const string ORIGIN = 'https://crewbids.kalittaair.com';

    private const array LOGIN_PATHS = ['/', '/default.aspx', '/login.aspx', '/pages/login.aspx'];

    public function __construct(private BidPortalHttpClient $http, private ReadBidPortalPage $pages) {}

    /**
     * Credentials, Web Forms state and cookies exist only during this callback.
     *
     * @template TResult of array<string, mixed>
     *
     * @param  Closure(string, Closure(string): string, Closure(array<string, string>): void): TResult  $operation
     * @return TResult
     */
    public function run(#[SensitiveParameter] string $employee, #[SensitiveParameter] string $password, Closure $operation): array
    {
        $cookies = new CookieJar;
        try {
            if (! preg_match('/\A\d{1,20}\z/', $employee) || $password === '') {
                throw new BidPortalException('Enter your employee number and portal password.');
            }
            [$loginUrl, $response] = $this->get(self::ORIGIN.'/', $cookies);
            $form = $this->pages->login($response->body());
            $action = $this->destination($loginUrl, $form['action']);
            if (! in_array(strtolower((new Uri($action))->getPath()), self::LOGIN_PATHS, true)) {
                throw new BidPortalException('The portal login destination is not supported.');
            }
            $fields = array_replace($form['fields'], ['Login1$UserName' => $employee, 'Login1$Password' => $password]);
            $login = $this->request('POST', $action, $cookies, $fields);
            if (in_array($login->status(), [302, 303], true)) {
                $landing = $this->destination($action, $login->header('Location'));
                if (strtolower((new Uri($landing))->getPath()) === '/redirect.aspx') {
                    $this->get($landing, $cookies);
                }
            } elseif (! $login->successful()) {
                throw new BidPortalException('Portal login failed or requires an unsupported authentication step.');
            }
            [$sheetUrl, $response] = $this->get(self::ORIGIN.'/Pages/BidSheet.aspx', $cookies);
            if ($sheetUrl !== self::ORIGIN.'/Pages/BidSheet.aspx') {
                throw new BidPortalException('Portal login was not accepted or the session expired.');
            }
            $get = function (#[SensitiveParameter] string $reference) use ($cookies, $sheetUrl): string {
                [$url, $response] = $this->get($this->destination($sheetUrl, $reference), $cookies);
                if (in_array(strtolower((new Uri($url))->getPath()), self::LOGIN_PATHS, true)) {
                    throw new BidPortalException('The portal session expired.');
                }

                return $response->body();
            };
            $post = function (#[SensitiveParameter] array $fields) use ($cookies, $sheetUrl): void {
                $response = $this->request('POST', $sheetUrl, $cookies, $fields);
                if (! $response->successful() && ! in_array($response->status(), [302, 303], true)) {
                    throw new BidPortalException('The portal update response could not be verified.');
                }
            };

            return $operation($response->body(), $get, $post);
        } catch (BidPortalException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new BidPortalException('The portal operation could not be completed. Credentials and page data were discarded.');
        } finally {
            $cookies->clear();
            unset($employee, $password, $fields, $form, $login, $response, $get, $post);
        }
    }

    /** @return array{string, Response} */
    private function get(string $url, #[SensitiveParameter] CookieJar $cookies): array
    {
        for ($redirects = 0; $redirects <= 5; $redirects++) {
            $url = $this->destination(self::ORIGIN.'/', $url);
            $response = $this->request('GET', $url, $cookies);
            if ($response->successful()) {
                return [$url, $response];
            }
            if (! in_array($response->status(), [301, 302, 303, 307, 308], true) || $response->header('Location') === '') {
                throw new BidPortalException('The portal page could not be read.');
            }
            $url = $this->destination($url, $response->header('Location'));
        }

        throw new BidPortalException('The portal exceeded the redirect limit.');
    }

    public function destination(string $base, #[SensitiveParameter] string $reference): string
    {
        $uri = UriResolver::resolve(new Uri($base), new Uri($reference));
        if ($uri->getScheme() !== 'https' || $uri->getHost() !== 'crewbids.kalittaair.com'
            || $uri->getPort() !== null || $uri->getUserInfo() !== '' || $uri->getFragment() !== ''
            || ! in_array(strtolower($uri->getPath()), [...self::LOGIN_PATHS, '/redirect.aspx', '/pages/bidsheet.aspx', '/pages/bidlinedataoutput.aspx', '/reserved.reportviewerwebcontrol.axd'], true)) {
            throw new BidPortalException('The portal supplied an unexpected destination.');
        }

        return (string) $uri;
    }

    /** @param array<string, string> $fields */
    private function request(string $method, string $url, #[SensitiveParameter] CookieJar $cookies, #[SensitiveParameter] array $fields = []): Response
    {
        $response = $this->http->asForm()->connectTimeout(5)->timeout(20)->withoutRedirecting()
            ->withHeaders(['Cache-Control' => 'no-cache, no-store', 'Pragma' => 'no-cache'])
            ->withOptions([
                'cookies' => $cookies, 'verify' => true,
                'on_headers' => static function (ResponseInterface $response): void {
                    if ((int) $response->getHeaderLine('Content-Length') > 8_000_000) {
                        throw new BidPortalException('The portal response is too large.');
                    }
                },
                'progress' => static function (float $total, float $downloaded): void {
                    if ($total > 8_000_000 || $downloaded > 8_000_000) {
                        throw new BidPortalException('The portal response is too large.');
                    }
                },
            ])->send($method, $url, $method === 'POST' ? ['form_params' => $fields] : []);
        if (strlen($response->body()) > 8_000_000) {
            throw new BidPortalException('The portal response is too large.');
        }

        return $response;
    }
}
