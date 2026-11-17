<?php

namespace Tests\Fixtures;

use App\Exceptions\BidPortalException;
use App\Services\CrewBids\CrewBidSecretStore;
use SensitiveParameter;

class InMemoryCrewBidSecretStore extends CrewBidSecretStore
{
    /** @var array<string, array{value: array<string, mixed>, expires: int}> */
    public array $entries = [];

    public bool $unavailable = false;

    public function available(): void
    {
        if ($this->unavailable) {
            throw new BidPortalException('The secure K4 memory store is unavailable. Try again after service is restored.');
        }
    }

    public function put(string $key, #[SensitiveParameter] array $value, int $seconds): void
    {
        $this->available();
        $this->entries[$key] = ['value' => $value, 'expires' => now()->timestamp + $seconds];
    }

    public function get(string $key): ?array
    {
        $this->available();
        $entry = $this->entries[$key] ?? null;

        return $entry !== null && $entry['expires'] > now()->timestamp ? $entry['value'] : null;
    }

    public function pull(string $key): ?array
    {
        $value = $this->get($key);
        $this->forget($key);

        return $value;
    }

    public function forget(string $key): void
    {
        $this->available();
        unset($this->entries[$key]);
    }

    public function replace(string $key, #[SensitiveParameter] array $before, #[SensitiveParameter] array $after, int $seconds): bool
    {
        if ($this->get($key) !== $before) {
            return false;
        }
        $this->put($key, $after, $seconds);

        return true;
    }
}
