<?php

namespace App\Services\Schedule;

use App\DTOs\AirportResolution;
use App\Services\Clients\AirportLookupClient;
use App\Services\Infrastructure\AirportCodeCache;
use App\ValueObjects\AirportCode;
use InvalidArgumentException;
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
        $validCodes = collect($codes)
            ->map(static function (string $code): ?AirportCode {
                try {
                    return AirportCode::from($code);
                } catch (InvalidArgumentException) {
                    return null;
                }
            })
            ->filter()
            ->unique(static fn (AirportCode $code): string => $code->value);

        $resolved = [];

        foreach ($validCodes as $airportCode) {
            $codeString = $airportCode->value;
            $cached = $this->cache->get($codeString);

            if ($cached !== null) {
                $resolved[$codeString] = $cached;

                continue;
            }

            try {
                $airport = $airportCode->isIata()
                    ? $this->client->lookupByIataOrFail($codeString)
                    : $this->client->lookupByIcaoOrFail($codeString);

                $resolution = $airport
                    ? AirportResolution::found($codeString, $airport)
                    : AirportResolution::missing($codeString);

                $this->cache->put($resolution);
                $resolved[$codeString] = $resolution;
            } catch (Throwable $exception) {
                report($exception);

                $resolution = AirportResolution::unavailable($codeString);
                $this->cache->put($resolution);
                $resolved[$codeString] = $resolution;
            }
        }

        return $resolved;
    }
}
