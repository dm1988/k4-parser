<?php

namespace App\Services\Clients;

use App\DTOs\AirportData;
use App\Exceptions\AirportResolutionException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class AirportLookupClient
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.airport_provider.url', 'http://localhost/api/v1');
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

            if ($response->successful()) {
                $data = $response->json('data');

                if (! is_array($data)) {
                    Log::warning('Airport lookup provider returned an unexpected response payload.', [
                        ...$this->lookupContext($payload),
                        'status' => $response->status(),
                    ]);

                    return $this->unavailableResult($throwOnUnavailable);
                }

                return AirportData::fromApi($data);
            }

            if ($response->status() === 404) {
                return null;
            }

            if ($response->status() === 422) {
                Log::warning('Airport lookup provider rejected a valid lookup request.', [
                    ...$this->lookupContext($payload),
                    'status' => $response->status(),
                ]);

                return $this->unavailableResult($throwOnUnavailable);
            }

            if (in_array($response->status(), [429, 500, 503], true)) {
                Log::warning('Airport lookup provider remained unavailable after retries.', [
                    ...$this->lookupContext($payload),
                    'status' => $response->status(),
                ]);

                return $this->unavailableResult($throwOnUnavailable);
            }

            Log::error('Airport lookup provider returned an unexpected error.', [
                ...$this->lookupContext($payload),
                'status' => $response->status(),
            ]);

            return $this->unavailableResult($throwOnUnavailable);
        } catch (ConnectionException $exception) {
            Log::warning('Airport lookup provider connection failed after retries.', [
                ...$this->lookupContext($payload),
                'exception' => $exception->getMessage(),
            ]);

            return $this->unavailableResult($throwOnUnavailable, $exception);
        }
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
}
