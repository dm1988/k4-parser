<?php

namespace App\Services;

use App\DTOs\AirportData;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AirportLookupClient
{
    protected string $baseUrl;

    public function __construct()
    {
        // Pull this from config/services.php or your .env file
        $this->baseUrl = config('services.airport_provider.url', 'http://localhost/api/v1');
    }

    /**
     * Look up an airport by its 3-letter IATA code.
     */
    public function lookupByIata(string $iata): ?AirportData
    {
        // Strip whitespace and force uppercase
        $cleanIata = strtoupper(trim($iata));

        // Fast-fail if structural constraint is unmet
        if (strlen($cleanIata) !== 3 || ! ctype_alpha($cleanIata)) {
            return null;
        }

        return $this->performLookup(['iata' => $cleanIata]);
    }

    /**
     * Look up an airport by its 4-letter ICAO code.
     */
    public function lookupByIcao(string $icao): ?AirportData
    {
        // Strip whitespace and force uppercase
        $cleanIcao = strtoupper(trim($icao));

        // Fast-fail if structural constraint is unmet
        if (strlen($cleanIcao) !== 4 || ! ctype_alpha($cleanIcao)) {
            return null;
        }

        return $this->performLookup(['icao' => $cleanIcao]);
    }

    /**
     * Sends the actual GET request and handles the response.
     */
    protected function performLookup(array $payload): ?AirportData
    {
        try {
            $response = Http::acceptJson()
                ->timeout(5)
                ->get("{$this->baseUrl}/airports/lookup", $payload);

            if ($response->successful()) {
                $data = $response->json('data');

                if (! is_array($data)) {
                    Log::warning('Airport lookup provider returned an unexpected response payload.', [
                        'payload' => $payload,
                        'body' => $response->body(),
                    ]);

                    return null;
                }

                return AirportData::fromApi($data);
            }

            if ($response->status() === 404) {
                return null;
            }

            if ($response->status() === 503) {
                Log::warning('Airport lookup provider is currently disabled.', ['payload' => $payload]);

                return null;
            }

            // Fallback for other errors (validation, 500s, etc.)
            Log::error('Airport lookup API failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Failed connecting to Airport lookup service.', ['exception' => $e->getMessage()]);

            return null;
        }
    }
}
