<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class AeroDataBoxClient
{
    private float $lastRequestStartedAtMs = 0.0;

    /** @return list<array<string, mixed>> */
    public function flightsByRegistration(
        string $tailNumber,
        CarbonInterface $dateFrom,
        CarbonInterface $dateTo,
    ): array {
        $baseUrl = rtrim((string) config('services.aerodatabox.base_url'), '/');
        $apiKey = (string) config('services.aerodatabox.key');

        if ($baseUrl === '' || $apiKey === '') {
            throw new InvalidArgumentException('AeroDataBox base URL and API key must be configured.');
        }

        $from = CarbonImmutable::instance($dateFrom)->utc()->startOfDay();
        $to = CarbonImmutable::instance($dateTo)->utc()->startOfDay();

        if ($to->lessThanOrEqualTo($from)) {
            throw new InvalidArgumentException('AeroDataBox flight search end date must be after its start date.');
        }

        $flights = [];
        $cursor = $from;

        while ($cursor->lessThan($to)) {
            $chunkEnd = $cursor->addDay()->min($to);

            foreach ($this->requestFlights($baseUrl, $apiKey, $tailNumber, $cursor, $chunkEnd) as $flight) {
                $flights[$this->flightKey($flight)] = $flight;
            }

            $cursor = $chunkEnd;
        }

        return array_values($flights);
    }

    /** @return list<array<string, mixed>> */
    private function requestFlights(
        string $baseUrl,
        string $apiKey,
        string $tailNumber,
        CarbonInterface $dateFrom,
        CarbonInterface $dateTo,
    ): array {
        $this->throttle();

        $response = Http::baseUrl($baseUrl)
            ->withHeaders(['x-magicapi-key' => $apiKey])
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(20)
            ->retry(3, 1000, $this->shouldRetry(...), throw: false)
            ->get(sprintf(
                'flights/Reg/%s/%s/%s',
                rawurlencode(strtoupper(trim($tailNumber))),
                $dateFrom->format('Y-m-d'),
                $dateTo->format('Y-m-d'),
            ), [
                'dateLocalRole' => 'Both',
            ]);

        if ($response->status() === 204 || $response->status() === 404) {
            return [];
        }

        $response->throw();
        $payload = $response->json();
        $flights = is_array($payload['items'] ?? null) ? $payload['items'] : $payload;

        if (! is_array($flights) || ! array_is_list($flights)) {
            throw new RuntimeException('AeroDataBox returned an unexpected flight response.');
        }

        return array_values(array_filter($flights, 'is_array'));
    }

    private function throttle(): void
    {
        $minimumIntervalMs = max((int) config('services.aerodatabox.throttle_ms', 1100), 0);

        if ($minimumIntervalMs === 0) {
            return;
        }

        $nowMs = hrtime(true) / 1_000_000;
        $remainingMs = $minimumIntervalMs - ($nowMs - $this->lastRequestStartedAtMs);

        if ($remainingMs > 0) {
            usleep((int) ceil($remainingMs * 1000));
        }

        $this->lastRequestStartedAtMs = hrtime(true) / 1_000_000;
    }

    /** @param array<string, mixed> $flight */
    private function flightKey(array $flight): string
    {
        $identity = [
            data_get($flight, 'aircraft.reg'),
            $flight['number'] ?? $flight['callSign'] ?? 'CHARTER',
            data_get($flight, 'departure.scheduledTime.utc')
                ?? data_get($flight, 'departure.actualTime.utc'),
            data_get($flight, 'departure.airport.iata') ?? data_get($flight, 'departure.airport.icao'),
            data_get($flight, 'arrival.airport.iata') ?? data_get($flight, 'arrival.airport.icao'),
        ];

        return hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR));
    }

    private function shouldRetry(Throwable $exception, PendingRequest $request): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        if (! $exception instanceof RequestException || $exception->response === null) {
            return false;
        }

        return $exception->response->status() === 429 || $exception->response->serverError();
    }
}
