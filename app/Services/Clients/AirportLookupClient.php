<?php

namespace App\Services\Clients;

use App\DTOs\AirportData;
use App\DTOs\AirportResolution;
use App\Exceptions\AirportResolutionException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class AirportLookupClient
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.airport_provider.url'), '/');
    }

    /**
     * Look up an airport by its 3-letter IATA code.
     */
    public function lookupByIata(string $iata): ?AirportData
    {
        $cleanIata = strtoupper(trim($iata));

        if (strlen($cleanIata) !== 3 || ! ctype_alpha($cleanIata)) {
            return null;
        }

        return $this->performLookup(['iata' => $cleanIata], false);
    }

    /**
     * Look up an airport by its 4-letter ICAO code.
     */
    public function lookupByIcao(string $icao): ?AirportData
    {
        $cleanIcao = strtoupper(trim($icao));

        if (strlen($cleanIcao) !== 4 || ! ctype_alpha($cleanIcao)) {
            return null;
        }

        return $this->performLookup(['icao' => $cleanIcao], false);
    }

    public function lookupByIataOrFail(string $iata): ?AirportData
    {
        $cleanIata = strtoupper(trim($iata));

        if (strlen($cleanIata) !== 3 || ! ctype_alpha($cleanIata)) {
            return null;
        }

        return $this->performLookup(['iata' => $cleanIata], true);
    }

    public function lookupByIcaoOrFail(string $icao): ?AirportData
    {
        $cleanIcao = strtoupper(trim($icao));

        if (strlen($cleanIcao) !== 4 || ! ctype_alpha($cleanIcao)) {
            return null;
        }

        return $this->performLookup(['icao' => $cleanIcao], true);
    }

    /**
     * @param  list<string>  $icaos
     * @return array<string, AirportResolution>
     */
    public function resolveByIcaos(array $icaos): array
    {
        $cleanIcaos = [];

        foreach ($icaos as $icao) {
            $cleanIcao = strtoupper(trim($icao));

            if (strlen($cleanIcao) === 4 && ctype_alpha($cleanIcao)) {
                $cleanIcaos[$cleanIcao] = $cleanIcao;
            }
        }

        if ($cleanIcaos === []) {
            return [];
        }

        $responses = Http::pool(function (Pool $pool) use ($cleanIcaos): array {
            $requests = [];

            foreach ($cleanIcaos as $icao) {
                $requests[] = $pool->as($icao)
                    ->acceptJson()
                    ->connectTimeout(2)
                    ->timeout(5)
                    ->retry(
                        [100, 250],
                        when: static fn (Throwable $exception): bool => $exception instanceof ConnectionException
                            || ($exception instanceof RequestException
                                && in_array($exception->response->status(), [429, 500, 503], true)),
                        throw: false,
                    )
                    ->get("{$this->baseUrl}/airports/lookup", ['icao' => $icao]);
            }

            return $requests;
        });

        $resolutions = [];

        foreach ($cleanIcaos as $icao) {
            $payload = ['icao' => $icao];
            $response = $responses[$icao] ?? null;

            if ($response instanceof Response) {
                $resolutions[$icao] = $this->resolutionFromResponse($response, $payload);

                continue;
            }

            if ($response instanceof Throwable) {
                Log::warning('Airport lookup provider connection failed after retries.', [
                    ...$this->lookupContext($payload),
                    'exception' => $response->getMessage(),
                ]);
            }

            $resolutions[$icao] = AirportResolution::unavailable($icao);
        }

        return $resolutions;
    }

    /**
     * Sends the actual GET request and handles the response.
     */
    protected function performLookup(array $payload, bool $throwOnUnavailable): ?AirportData
    {
        try {
            $response = Http::acceptJson()
                ->connectTimeout(2)
                ->timeout(5)
                ->retry(
                    [100, 250],
                    when: static fn (Throwable $exception): bool => $exception instanceof ConnectionException
                        || ($exception instanceof RequestException
                            && in_array($exception->response->status(), [429, 500, 503], true)),
                    throw: false,
                )
                ->get("{$this->baseUrl}/airports/lookup", $payload);

            $resolution = $this->resolutionFromResponse($response, $payload);

            if ($resolution->isUnavailable()) {
                return $this->unavailableResult($throwOnUnavailable);
            }

            return $resolution->airport;
        } catch (ConnectionException $exception) {
            Log::warning('Airport lookup provider connection failed after retries.', [
                ...$this->lookupContext($payload),
                'exception' => $exception->getMessage(),
            ]);

            return $this->unavailableResult($throwOnUnavailable, $exception);
        }
    }

    /**
     * @param  array<string, string>  $payload
     */
    private function resolutionFromResponse(Response $response, array $payload): AirportResolution
    {
        $requestedCode = $this->requestedCode($payload);

        if ($response->successful()) {
            $data = $response->json('data');

            if (! is_array($data)) {
                Log::warning('Airport lookup provider returned an unexpected response payload.', [
                    ...$this->lookupContext($payload),
                    'status' => $response->status(),
                ]);

                return AirportResolution::unavailable($requestedCode);
            }

            return AirportResolution::found($requestedCode, AirportData::fromApi($data));
        }

        if ($response->status() === 404) {
            return AirportResolution::missing($requestedCode);
        }

        if ($response->status() === 422) {
            Log::warning('Airport lookup provider rejected a valid lookup request.', [
                ...$this->lookupContext($payload),
                'status' => $response->status(),
            ]);

            return AirportResolution::unavailable($requestedCode);
        }

        if (in_array($response->status(), [429, 500, 503], true)) {
            Log::warning('Airport lookup provider remained unavailable after retries.', [
                ...$this->lookupContext($payload),
                'status' => $response->status(),
            ]);

            return AirportResolution::unavailable($requestedCode);
        }

        Log::error('Airport lookup provider returned an unexpected error.', [
            ...$this->lookupContext($payload),
            'status' => $response->status(),
        ]);

        return AirportResolution::unavailable($requestedCode);
    }

    private function unavailableResult(bool $throwOnUnavailable, ?Throwable $previous = null): null
    {
        if ($throwOnUnavailable) {
            throw AirportResolutionException::providerUnavailable($previous);
        }

        return null;
    }

    /**
     * @param  array<string, string>  $payload
     * @return array{lookup_type: string, lookup_code: string}
     */
    private function lookupContext(array $payload): array
    {
        $lookupType = array_key_first($payload) ?? 'unknown';

        return [
            'lookup_type' => $lookupType,
            'lookup_code' => $payload[$lookupType] ?? '',
        ];
    }

    /**
     * @param  array<string, string>  $payload
     */
    private function requestedCode(array $payload): string
    {
        $lookupType = array_key_first($payload);

        return $lookupType === null ? '' : $payload[$lookupType];
    }
}
