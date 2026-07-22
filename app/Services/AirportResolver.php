<?php

namespace App\Services;

use App\DTOs\AirportResolution;
use Throwable;

final class AirportResolver
{
    public function __construct(
        private readonly AirportLookupClient $client,
        private readonly AirportCodeCache $cache,
    ) {}

    /**
     * @param  iterable<string>  $codes
     * @return array<string, AirportResolution>
     */
    public function resolveMany(iterable $codes): array
    {
        $codes = collect($codes)
            ->map(static fn (string $code): string => strtoupper(trim($code)))
            ->filter(static fn (string $code): bool => preg_match('/^[A-Z]{3,4}$/', $code) === 1)
            ->unique()
            ->values();

        $resolved = [];

        foreach ($codes as $code) {
            $cached = $this->cache->get($code);

            if ($cached !== null) {
                $resolved[$code] = $cached;

                continue;
            }

            try {
                $airport = strlen($code) === 3
                    ? $this->client->lookupByIataOrFail($code)
                    : $this->client->lookupByIcaoOrFail($code);

                $resolution = $airport
                    ? AirportResolution::found($code, $airport)
                    : AirportResolution::missing($code);

                $this->cache->put($resolution);
                $resolved[$code] = $resolution;
            } catch (Throwable $exception) {
                report($exception);

                $resolution = AirportResolution::unavailable($code);
                $this->cache->put($resolution);
                $resolved[$code] = $resolution;
            }
        }

        return $resolved;
    }
}
